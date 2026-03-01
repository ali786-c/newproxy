<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Models\Coupon;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Models\FulfillmentLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\CryptomusHelper;
use App\Services\ReferralService;

class BillingController extends Controller
{
    public function createProductCheckout(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'coupon_code' => 'nullable|string',
        ]);

        $product = \App\Models\Product::findOrFail($request->product_id);
        $totalAmount = $product->price * $request->quantity;
        $originalAmount = $totalAmount;
        $couponCode = $request->coupon_code;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon && $coupon->isValid($totalAmount)) {
                $discount = $coupon->calculateDiscount($totalAmount);
                $totalAmount = max(0, $totalAmount - $discount);
            }
        }

        $stripeSecret = Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret');
        if (!$stripeSecret) {
            Log::error('Stripe product checkout failed: No Stripe secret key configured.');
            return response()->json(['message' => 'Payment gateway is not configured. Please contact support.'], 503);
        }

        try {
            Stripe::setApiKey($stripeSecret);

            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => "{$request->quantity}x {$product->name}",
                            'description' => "Direct purchase of {$request->quantity}x {$product->name} proxies",
                        ],
                        'unit_amount' => round($totalAmount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/') . '/app/proxies/generate?success=true&direct=true&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/') . '/app/proxies/generate?canceled=true',
                'metadata' => [
                    'user_id' => $request->user()->id,
                    'type' => 'direct_purchase',
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'country' => $request->country ?? 'US',
                    'session_type' => $request->session_type ?? 'rotating',
                    'amount' => $totalAmount,
                    'original_amount' => $originalAmount,
                    'coupon_code' => $couponCode,
                ],
            ];

            if ($request->user()->stripe_customer_id) {
                $sessionData['customer'] = $request->user()->stripe_customer_id;
            }

            $session = Session::create($sessionData);

            return response()->json(['url' => $session->url]);

        } catch (\Stripe\Exception\AuthenticationException $e) {
            Log::error('Stripe Auth Error (Product): ' . $e->getMessage());
            return response()->json(['message' => 'Payment gateway authentication failed. Please contact support.'], 503);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API Error (Product): ' . $e->getMessage());
            return response()->json(['message' => 'Payment error: ' . $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Product Checkout Error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error during checkout. Please try again.'], 500);
        }
    }

    public function createCheckout(Request $request)
    {
        $request->validate([
            'total_amount'  => 'required|numeric|min:0.01', // Final total user saw (includes VAT)
            'net_amount'    => 'required|numeric|min:0.01', // Pre-VAT amount (credited to wallet)
            'currency_code' => 'nullable|string|max:10',
        ]);

        $totalInput    = (float) $request->total_amount;   // e.g. 28.06 EUR or 30.61 USD
        $netInput      = (float) $request->net_amount;     // e.g. 23.00 EUR or 25.09 USD (pre-VAT)
        $inputCurrency = strtoupper($request->currency_code ?? 'EUR');

        // ── Convert to EUR ──────────────────────────────────────────────────────
        // Exchange rates are now EUR-based: exchange_rate = "1 EUR = X units of currency"
        // So: amount_in_eur = amount_in_currency / exchange_rate
        //
        // EUR itself has exchange_rate = 1.0 → no conversion needed
        $totalInEur = $totalInput;
        $netInEur   = $netInput;

        if ($inputCurrency !== 'EUR') {
            $currencyModel = \App\Models\SupportedCurrency::where('code', $inputCurrency)->first();

            if ($currencyModel && $currencyModel->exchange_rate > 0) {
                // exchange_rate = 1 EUR = X units → divide to get EUR
                $totalInEur = $totalInput / $currencyModel->exchange_rate;
                $netInEur   = $netInput / $currencyModel->exchange_rate;
            }
            // If currency not found, fall back: treat as EUR
        }

        // Round to 2dp
        $totalInEur = round($totalInEur, 2);
        $netInEur   = round($netInEur, 2);

        // ── Stripe ─────────────────────────────────────────────────────────────
        // totalInEur is the FINAL amount that includes VAT — send directly to Stripe.
        // NO additional VAT multiplication here.
        $stripeAmountCents = (int) round($totalInEur * 100);

        $stripeSecret = Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret');
        if (!$stripeSecret) {
            Log::error('Stripe checkout failed: No Stripe secret key configured.');
            return response()->json(['message' => 'Payment gateway is not configured. Please contact support.'], 503);
        }

        try {
            Stripe::setApiKey($stripeSecret);

            $description = 'Add funds to your UpgradedProxy wallet';
            if ($inputCurrency !== 'EUR') {
                $description .= " (converted from {$inputCurrency} {$totalInput} → €{$totalInEur})";
            }

            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'eur',
                        'product_data' => [
                            'name'        => 'Balance Top-up',
                            'description' => $description,
                        ],
                        'unit_amount'  => $stripeAmountCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode'        => 'payment',
                'success_url' => url('/') . '/app/billing?success=true&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => url('/') . '/app/billing?canceled=true',
                'metadata'    => [
                    'user_id'         => (string) $request->user()->id,
                    'type'            => 'topup',
                    'amount'          => (string) $netInEur,           // net credited to wallet
                    'original_amount' => (string) $netInEur,           // fallback for fulfillPayment
                    'total_charged'   => (string) $totalInEur,         // total user paid
                    'input_currency'  => (string) $inputCurrency,
                    'input_total'     => (string) $totalInput,
                    'input_net'       => (string) $netInput,
                    'coupon_code'     => (string) ($request->coupon_code ?? ''),
                ],
            ];

            if ($request->user()->stripe_customer_id) {
                $sessionData['customer'] = $request->user()->stripe_customer_id;
            }

            $session = Session::create($sessionData);

            return response()->json(['url' => $session->url]);

        } catch (\Stripe\Exception\AuthenticationException $e) {
            Log::error('Stripe Auth Error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment gateway authentication failed. Please contact support.'], 503);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment error: ' . $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Checkout Error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error during checkout. Please try again.'], 500);
        }
    }

    public function createCryptomusCheckout(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'coupon_code' => 'nullable|string',
        ]);

        $amount = (float) $request->amount;
        $originalAmount = $amount;
        $couponCode = $request->coupon_code;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon && $coupon->isValid($amount)) {
                $discount = $coupon->calculateDiscount($amount);
                $amount = max(0, $amount - $discount);
            }
        }

        $merchantId = Setting::getValue('cryptomus_merchant_id');
        $apiKey = Setting::getValue('cryptomus_api_key');

        if (!$merchantId || !$apiKey) {
            return response()->json(['error' => 'Cryptomus is not configured.'], 400);
        }

        $orderId = 'TOPUP-' . time() . '-' . $request->user()->id;
        
        $data = [
            'amount' => (string) $amount,
            'currency' => 'EUR', // Primary app currency
            'order_id' => $orderId,
            'url_return' => url('/') . '/app/billing?success=true&gateway=cryptomus',
            'url_callback' => url('/') . '/api/webhook/cryptomus',
            'additional_data' => json_encode([
                'user_id' => $request->user()->id,
                'type' => 'topup',
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'coupon_code' => $couponCode,
            ]),
        ];

        $sign = CryptomusHelper::generateSignature($data, $apiKey);

        $response = Http::withHeaders([
            'merchant' => $merchantId,
            'sign' => $sign,
        ])->post('https://api.cryptomus.com/v1/payment', $data);

        if ($response->successful()) {
            return response()->json(['url' => $response->json('result.url')]);
        }

        Log::error('Cryptomus API Error: ' . $response->body());
        return response()->json(['error' => 'Could not create crypto payment.'], 500);
    }

    public function createCryptomusProductCheckout(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'coupon_code' => 'nullable|string',
        ]);

        $product = \App\Models\Product::findOrFail($request->product_id);
        $totalAmount = $product->price * $request->quantity;
        $originalAmount = $totalAmount;
        $couponCode = $request->coupon_code;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon && $coupon->isValid($totalAmount)) {
                $discount = $coupon->calculateDiscount($totalAmount);
                $totalAmount = max(0, $totalAmount - $discount);
            }
        }

        $merchantId = Setting::getValue('cryptomus_merchant_id');
        $apiKey = Setting::getValue('cryptomus_api_key');

        if (!$merchantId || !$apiKey) {
            return response()->json(['error' => 'Cryptomus is not configured.'], 400);
        }

        $orderId = 'PROD-' . time() . '-' . $request->user()->id;

        $data = [
            'amount' => (string) $totalAmount,
            'currency' => 'EUR',
            'order_id' => $orderId,
            'url_return' => url('/') . '/app/proxies/generate?success=true&direct=true&gateway=cryptomus',
            'url_callback' => url('/') . '/api/webhook/cryptomus',
            'additional_data' => json_encode([
                'user_id' => $request->user()->id,
                'type' => 'direct_purchase',
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'country' => $request->country ?? 'US',
                'session_type' => $request->session_type ?? 'rotating',
                'amount' => $totalAmount,
                'original_amount' => $originalAmount,
                'coupon_code' => $couponCode,
            ]),
        ];

        $sign = CryptomusHelper::generateSignature($data, $apiKey);

        $response = Http::withHeaders([
            'merchant' => $merchantId,
            'sign' => $sign,
        ])->post('https://api.cryptomus.com/v1/payment', $data);

        if ($response->successful()) {
            return response()->json(['url' => $response->json('result.url')]);
        }

        Log::error('Cryptomus API Error: ' . $response->body());
        return response()->json(['error' => 'Could not create crypto payment.'], 500);
    }

    public function handleCryptomusWebhook(Request $request)
    {
        // IP Whitelisting (Optional but recommended by Cryptomus)
        $allowedIps = ['91.227.144.54'];
        if (!in_array($request->ip(), $allowedIps) && !app()->environment('local')) {
            Log::warning("Cryptomus Webhook Warning: Request from unauthorized IP: " . $request->ip());
            // We only warn for now to avoid breaking existing setups if IPs change
        }

        $data = $request->all();
        $apiKey = Setting::getValue('cryptomus_webhook_secret') ?: Setting::getValue('cryptomus_api_key');

        if (!$apiKey) {
            Log::error('Cryptomus Webhook Error: Missing API key.');
            return response()->json(['error' => 'API Key not configured'], 401);
        }

        if (!CryptomusHelper::verifySignature($data, $apiKey)) {
            Log::error('Cryptomus Webhook Error: Signature mismatch.');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $status = $data['status'] ?? 'unknown';

        // Handle terminal failure states
        if (in_array($status, ['fail', 'cancel', 'system_fail'])) {
            Log::warning("Cryptomus Payment Terminal Failure: User redirected or process failed. Status: {$status}, UUID: " . ($data['uuid'] ?? 'N/A'));
            return response()->json(['message' => 'Failure recorded']);
        }

        // Handle Partial Payments (VERY IMPORTANT)
        if ($status === 'wrong_amount') {
            Log::error("Cryptomus Webhook ALERT: Partial payment detected. UUID: {$data['uuid']}. User paid {$data['payment_amount']} {$data['currency']} instead of {$data['amount']} {$data['currency']}. Manual intervention required.");
            
            // Mark invoice as failed/wrong_amount in internal records
            $metadata = isset($data['additional_data']) ? json_decode($data['additional_data'], true) : null;
            if ($metadata && isset($metadata['user_id'])) {
                \App\Models\Invoice::create([
                    'user_id' => $metadata['user_id'],
                    'amount' => $data['payment_amount'] ?? 0,
                    'currency' => $data['currency'] ?? 'EUR',
                    'status' => 'failed',
                    'description' => "Cryptomus Partial Payment: Received {$data['payment_amount']} instead of {$data['amount']}",
                ]);
            }
            
            return response()->json(['message' => 'Partial payment logged']);
        }

        // Only process successful payments (including paid_over)
        if (!in_array($status, ['paid', 'paid_over', 'completed'])) {
            return response()->json(['message' => 'Payment status ignored: ' . $status]);
        }

        $uuid = $data['uuid'] ?? null;
        if (!$uuid || WebhookLog::where('event_id', $uuid)->exists()) {
            return response()->json(['message' => 'Already processed or invalid UUID']);
        }

        $metadata = isset($data['additional_data']) ? json_decode($data['additional_data'], true) : null;
        if (!$metadata) {
            // Fallback to order_id parsing if metadata missing
            $orderId = $data['order_id'] ?? '';
            // TOPUP-12345678-USERID
            $parts = explode('-', $orderId);
            $metadata = [
                'user_id' => end($parts),
                'type' => str_starts_with($orderId, 'PROD-') ? 'direct_purchase' : 'topup',
                'amount' => $data['amount'] ?? 0,
            ];
        }

        $this->fulfillCryptomusPayment($data, $uuid, $metadata);

        return response()->json(['status' => 'success']);
    }

    protected function fulfillCryptomusPayment($data, $uuid, $metadata)
    {
        $userId = $metadata['user_id'] ?? null;
        $type   = $metadata['type'] ?? 'topup';
        $amount = (float) ($metadata['amount'] ?? ($data['amount'] ?? 0));

        if (!$userId) {
            Log::error("Cryptomus Webhook Error: No user_id in metadata for UUID {$uuid}");
            return;
        }

        Log::info("START Cryptomus Fulfillment for User #{$userId}, UUID: {$uuid}");

        $referralService = app(ReferralService::class);

        // Create initial Fulfillment Monitoring Log
        $fulfillmentLog = FulfillmentLog::updateOrCreate(
            ['event_id' => $uuid],
            [
                'user_id'  => $userId,
                'provider' => 'cryptomus',
                'type'     => $type,
                'stage'    => 1,
                'status'   => 'processing',
                'details'  => [
                    'uuid'     => $uuid,
                    'amount'   => $amount,
                    'currency' => $data['currency'] ?? 'EUR',
                    'metadata' => $metadata,
                    'raw_data' => $data
                ]
            ]
        );

        // ══════════════════════════════════════════════════════════════
        // STAGE 1: COMMIT PAYMENT RECORD (Isolated — never rolls back)
        // ══════════════════════════════════════════════════════════════
        try {
            DB::transaction(function () use ($userId, $amount, $uuid, $type, $metadata, $data, $fulfillmentLog) {
                $user = User::lockForUpdate()->find($userId);
                if (!$user) return;

                // Permanently record the invoice
                \App\Models\Invoice::updateOrCreate(
                    ['stripe_invoice_id' => "CRYPTO-{$uuid}"],
                    [
                        'user_id'     => $user->id,
                        'amount'      => $amount,
                        'currency'    => $data['currency'] ?? 'EUR',
                        'status'      => 'paid',
                        'description' => 'Wallet Top-up',
                    ]
                );

                // Mark as processed
                WebhookLog::updateOrCreate(
                    ['event_id' => $uuid],
                    ['provider' => 'cryptomus']
                );

                // --- NEW: Trigger Order Confirmation Email ---
                try {
                    $user->notify(new \App\Notifications\OrderConfirmationNotification([
                        'user' => ['name' => $user->name],
                        'order' => [
                            'id' => $uuid,
                            'amount' => ($data['currency'] ?? 'EUR') . ' ' . $amount
                        ],
                        'action_url' => url('/app/billing'),
                        'year' => date('Y')
                    ]));
                    
                    // Alert Admin
                    $adminEmail = \App\Models\Setting::getValue('admin_notification_email', 'aliyantarar4@gmail.com');
                    \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                        ->notify(new \App\Notifications\GenericDynamicNotification('admin_new_order', [
                            'user' => ['email' => $user->email],
                            'order' => [
                                'id' => $uuid,
                                'amount' => ($data['currency'] ?? 'EUR') . ' ' . $amount
                            ],
                            'admin_url' => url('/admin/billing'),
                            'year' => date('Y')
                        ]));
                } catch (\Exception $e) {
                    \Log::error("Order Confirmation Email Error: " . $e->getMessage());
                }
                // ----------------------------------------------

                $fulfillmentLog->update(['stage' => 1, 'status' => 'success']);
            });
            Log::info("STAGE 1 OK: Cryptomus Invoice committed for User #{$userId}, UUID: {$uuid}");
        } catch (\Exception $e) {
            $fulfillmentLog->update([
                'status'        => 'failed',
                'error_message' => "Stage 1 (Billing) Failed: " . $e->getMessage()
            ]);
            Log::error("STAGE 1 FAILED for Cryptomus User #{$userId}. Error: " . $e->getMessage());
            return;
        }

        // ══════════════════════════════════════════════════════════════
        // STAGE 2 & 3: PRODUCT DELIVERY — independent of Stage 1.
        // ══════════════════════════════════════════════════════════════
        $user = User::find($userId);
        if (!$user) return;

        // Regular Wallet Top-up
        $fulfillmentLog->update(['stage' => 2, 'status' => 'success']);
        $originalAmount = (float) ($metadata['original_amount'] ?? $amount);
        $couponCode     = $metadata['coupon_code'] ?? null;
        $creditAmount   = $couponCode ? $originalAmount : $amount;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon) $coupon->increment('used_count');
        }

        DB::transaction(function () use ($user, $creditAmount, $amount, $couponCode, $uuid, $referralService, $fulfillmentLog) {
            $fulfillmentLog->update(['stage' => 3, 'status' => 'success']);
            $user->increment('balance', $creditAmount);
            WalletTransaction::create([
                'user_id'     => $user->id,
                'type'        => 'credit',
                'amount'      => $creditAmount,
                'reference'   => "CRYPTO-{$uuid}",
                'description' => 'Cryptomus Wallet Top-up' . ($couponCode ? " (Used promo: {$couponCode})" : ''),
            ]);
            $referralService->awardCommission($user, $amount, "Commission from Wallet Top-up (Cryptomus)");
        });

        Log::info("CRYPTOMUS FULFILLMENT COMPLETE for User #{$userId}, UUID: {$uuid}. Type: {$type}");
    }

    /**
     * Verify a completed Stripe Checkout Session when user returns to success URL.
     * This acts as a guaranteed fallback in case the webhook is delayed or fails.
     * POST /billing/verify-session
     */
    public function verifySession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string|starts_with:cs_',
        ]);

        $stripeSecret = Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret');
        if (!$stripeSecret) {
            return response()->json(['message' => 'Payment gateway not configured.'], 503);
        }

        try {
            Stripe::setApiKey($stripeSecret);
            $session = Session::retrieve($request->session_id);
        } catch (\Exception $e) {
            Log::error('verifySession Stripe Error: ' . $e->getMessage());
            return response()->json(['message' => 'Could not verify session.'], 422);
        }

        // Only process paid sessions
        if ($session->payment_status !== 'paid') {
            return response()->json(['status' => 'pending', 'message' => 'Payment not yet completed.']);
        }

        // Idempotency: already handled?
        // We use the Session ID (cs_...) as the primary reference for top-ups
        if (WebhookLog::where('event_id', $session->id)->exists()) {
            Log::info("verifySession: Session {$session->id} already processed. Skipping.");
            return response()->json(['status' => 'already_processed', 'message' => 'Payment already credited.']);
        }

        // Verify the session belongs to the authenticated user (security check)
        $authenticatedUserId = $request->user()->id;
        // Robust metadata extraction in verifySession
        $metadata = $session->metadata;
        $sessionUserId = isset($metadata['user_id']) ? $metadata['user_id'] : ($metadata->user_id ?? null);

        if (!$sessionUserId || (string)$sessionUserId !== (string)$authenticatedUserId) {
            Log::warning("verifySession: User #{$authenticatedUserId} tried to claim session. Session Metadata User ID: " . ($sessionUserId ?? 'NULL'));
            return response()->json(['message' => 'Session does not belong to this account.'], 403);
        }

        // Fulfill the payment (same logic as webhook)
        $this->fulfillPayment($session, $session->id);

        return response()->json([
            'status' => 'fulfilled',
            'message' => 'Payment verified and balance updated successfully.',
        ]);
    }

    public function handleWebhook(Request $request)

    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = Setting::getValue('stripe_webhook_secret') ?: config('services.stripe.webhook_secret');
        Stripe::setApiKey(Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret'));
        Stripe::setApiVersion('2024-04-10');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error("Webhook Signature Verification Failed: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Idempotency Check
        if (WebhookLog::where('event_id', $event->id)->exists()) {
            return response()->json(['message' => 'Event already processed'], 200);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if ($session->mode === 'payment') {
                    // Use $session->id (cs_...) as the primary reference for top-ups to match verifySession
                    $this->fulfillPayment($session, $session->id);
                } elseif ($session->mode === 'setup') {
                    $this->fulfillSetup($session);
                }
                break;
            case 'invoice.paid':
                $this->handleInvoicePaid($event->data->object, $event->id);
                break;
            case 'setup_intent.succeeded':
                $this->updateUserPaymentMethod($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object, $event->id);
                break;
            case 'checkout.session.expired':
                $this->handleSessionExpired($event->data->object, $event->id);
                break;
            case 'invoice.payment_failed':
                $this->handleInvoiceFailed($event->data->object, $event->id);
                break;
            case 'invoice.voided':
                $this->handleInvoiceVoided($event->data->object, $event->id);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    protected function handleInvoicePaid($invoice, $eventId)
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();
        if (!$user) return;

        DB::transaction(function () use ($user, $invoice, $eventId) {
            // Log formal invoice
            \App\Models\Invoice::updateOrCreate(
                ['stripe_invoice_id' => $invoice->id],
                [
                    'user_id' => $user->id,
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => $invoice->currency,
                    'status' => 'paid',
                    'pdf_url' => $invoice->invoice_pdf,
                    'description' => $invoice->description ?? 'Invoice for payment',
                ]
            );

            WebhookLog::create([
                'provider' => 'stripe',
                'event_id' => $eventId,
            ]);
        });

        Log::info("Invoice paid and logged for user {$user->id}: {$invoice->id}");
    }

    protected function handleInvoiceFailed($invoice, $eventId)
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();
        if (!$user) return;

        \App\Models\Invoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'status' => 'failed',
                'description' => $invoice->last_payment_error ? $invoice->last_payment_error->message : 'Payment failed',
            ]
        );

        WebhookLog::create([
            'provider' => 'stripe',
            'event_id' => $eventId,
        ]);

        Log::warning("Invoice payment failed for user {$user->id}: {$invoice->id}");
    }

    protected function handleInvoiceVoided($invoice, $eventId)
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();
        if (!$user) return;

        \App\Models\Invoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'status' => 'voided',
            ]
        );

        WebhookLog::create([
            'provider' => 'stripe',
            'event_id' => $eventId,
        ]);

        Log::info("Invoice voided for user {$user->id}: {$invoice->id}");
    }

    protected function handlePaymentFailed($intent, $eventId)
    {
        $userId = $intent->metadata->user_id ?? null;
        $error = $intent->last_payment_error ? $intent->last_payment_error->message : 'Unknown error';

        Log::warning("Stripe Payment Failed for User #{$userId}: {$error}");

        WebhookLog::create([
            'provider' => 'stripe',
            'event_id' => $eventId,
        ]);
    }

    protected function handleSessionExpired($session, $eventId)
    {
        $userId = $session->metadata->user_id ?? 'unknown';
        Log::info("Stripe Checkout Session Expired for User #{$userId}: {$session->id}");

        WebhookLog::create([
            'provider' => 'stripe',
            'event_id' => $eventId,
        ]);
    }

    protected function fulfillPayment($session, $eventId)
    {
        $referralService = app(ReferralService::class);
        
        // ULTIMATE Metadata Extraction (Stripe library versions can vary)
        $metadata = $session->metadata;
        $userId   = isset($metadata['user_id']) ? $metadata['user_id'] : ($metadata->user_id ?? null);

        if (!$userId) {
            Log::critical("CRITICAL Fulfillment Error: No user_id found in metadata for session {$session->id}. Full session: " . json_encode($session->toArray()));
            return;
        }

        Log::info("START Fulfilling payment for User #{$userId}, Reference: {$eventId}");

        $type           = isset($metadata['type']) ? $metadata['type'] : ($metadata->type ?? 'topup');
        $amount         = (float) (isset($metadata['amount']) ? $metadata['amount'] : ($metadata->amount ?? 0));
        $originalAmount = (float) (isset($metadata['original_amount']) ? $metadata['original_amount'] : ($metadata->original_amount ?? $amount));
        $couponCode     = isset($metadata['coupon_code']) ? $metadata['coupon_code'] : ($metadata->coupon_code ?? null);
        $paidGross      = isset($session->amount_total) ? $session->amount_total / 100 : 0;
        $currency       = strtoupper($session->currency ?? 'EUR');

        // Create initial Fulfillment Monitoring Log
        $fulfillmentLog = FulfillmentLog::updateOrCreate(
            ['event_id' => $session->id],
            [
                'user_id'  => $userId,
                'provider' => 'stripe',
                'type'     => $type,
                'stage'    => 1,
                'status'   => 'processing',
                'details'  => [
                    'session_id' => $session->id,
                    'amount'     => $paidGross,
                    'currency'   => $currency,
                    'metadata'   => $metadata
                ]
            ]
        );

        // ══════════════════════════════════════════════════════════════
        // STAGE 1: COMMIT PAYMENT RECORD (Isolated — never rolls back)
        // Permanently record the payment before touching any external API.
        // ══════════════════════════════════════════════════════════════
        try {
            DB::transaction(function () use ($userId, $couponCode, $paidGross, $eventId, $type, $session, $currency) {
                $user = User::lockForUpdate()->find($userId);
                if (!$user) return;

                // Track coupon usage
                if ($couponCode) {
                    $coupon = Coupon::where('code', $couponCode)->first();
                    if ($coupon) $coupon->increment('used_count');
                }

                // Save Stripe Customer ID if missing
                if (!$user->stripe_customer_id && $session->customer) {
                    $user->update(['stripe_customer_id' => $session->customer]);
                }

                // Permanently record the invoice
                \App\Models\Invoice::updateOrCreate(
                    ['stripe_invoice_id' => $session->id],
                    [
                        'user_id'     => $user->id,
                        'amount'      => $paidGross,
                        'currency'    => $currency,
                        'status'      => 'paid',
                        'description' => 'Wallet Top-up',
                    ]
                );

                // Mark as processed so webhook doesn't double-process
                WebhookLog::updateOrCreate(
                    ['event_id' => $eventId],
                    ['provider' => 'stripe']
                );

                // --- NEW: Trigger Order Confirmation Email ---
                try {
                    $user->notify(new \App\Notifications\OrderConfirmationNotification([
                        'user' => ['name' => $user->name],
                        'order' => [
                            'id' => $session->id,
                            'amount' => $currency . ' ' . $paidGross
                        ],
                        'action_url' => url('/app/billing'),
                        'year' => date('Y')
                    ]));

                    // Alert Admin
                    $adminEmail = \App\Models\Setting::getValue('admin_notification_email', 'aliyantarar4@gmail.com');
                    \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                        ->notify(new \App\Notifications\GenericDynamicNotification('admin_new_order', [
                            'user' => ['email' => $user->email],
                            'order' => [
                                'id' => $session->id,
                                'amount' => $currency . ' ' . $paidGross
                            ],
                            'admin_url' => url('/admin/billing'),
                            'year' => date('Y')
                        ]));
                } catch (\Exception $e) {
                    \Log::error("Stripe Order Confirmation Email Error: " . $e->getMessage());
                }
                // ----------------------------------------------
            });
            $fulfillmentLog->update(['stage' => 1, 'status' => 'success']);
            Log::info("STAGE 1 OK: Invoice & WebhookLog committed for User #{$userId}, Ref: {$eventId}");
        } catch (\Exception $e) {
            $fulfillmentLog->update([
                'status'        => 'failed',
                'error_message' => "Stage 1 (Billing) Failed: " . $e->getMessage()
            ]);
            Log::error("STAGE 1 FAILED for User #{$userId}. Error: " . $e->getMessage());
            // Cannot even log the payment — stop here. Something is fundamentally broken (e.g., DB is down).
            return;
        }

        // ══════════════════════════════════════════════════════════════
        // STAGE 2 & 3: PRODUCT DELIVERY — fully independent of Stage 1.
        // A failure here will NEVER roll back the invoice above.
        // ══════════════════════════════════════════════════════════════
        $user = User::find($userId);
        if (!$user) return;

        // Regular Wallet Top-up
        $fulfillmentLog->update(['stage' => 2, 'status' => 'success']); // Stage 2 is implicit for topups
        $creditAmount = $originalAmount ?: $amount;
        if ($creditAmount > 0) {
            DB::transaction(function () use ($user, $creditAmount, $amount, $couponCode, $eventId, $currency, $userId, $referralService, $fulfillmentLog) {
                $fulfillmentLog->update(['stage' => 3, 'status' => 'success']);
                $user->increment('balance', $creditAmount);
                Log::info("Wallet top-up for User #{$userId}: +{$currency} {$creditAmount} (Ref: {$eventId})");
                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'type'        => 'credit',
                    'amount'      => $creditAmount,
                    'reference'   => $eventId,
                    'description' => 'Stripe Wallet Top-up' . ($couponCode && !empty($couponCode) ? " (Used promo: {$couponCode})" : ''),
                ]);
                $referralService->awardCommission($user, $amount, "Commission from Wallet Top-up (Stripe)");
            });
        } else {
            Log::warning("Zero amount top-up for User #{$userId}. Amount: {$amount}, Original: {$originalAmount}");
            $fulfillmentLog->update(['stage' => 3, 'status' => 'success', 'error_message' => 'Zero amount top-up skipped']);
        }

        Log::info("FULFILLMENT COMPLETE for User #{$userId}, Reference: {$eventId}. Type: {$type}");
    }

    protected function fulfillSetup($session)
    {
        $user = User::where('stripe_customer_id', $session->customer)->first();
        if ($user && $session->setup_intent) {
            Stripe::setApiKey(Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret'));
            $setupIntent = \Stripe\SetupIntent::retrieve($session->setup_intent);
            if ($setupIntent->payment_method) {
                $user->update(['default_payment_method' => $setupIntent->payment_method]);
                Log::info("Default payment method updated for user {$user->id}");
            }
        }
    }

    protected function updateUserPaymentMethod($setupIntent)
    {
        $user = User::where('stripe_customer_id', $setupIntent->customer)->first();
        if ($user) {
            $user->update(['default_payment_method' => $setupIntent->payment_method]);
            Log::info("Default payment method updated for user {$user->id} via setup_intent.succeeded");
        }
    }

    public function submitCrypto(Request $request)
{
    $request->validate([
        'currency'   => 'required|string',
        'amount'     => 'required|numeric|min:1',
        'txid'       => 'nullable|string',
        'binance_id' => 'nullable|string',
        'proof'      => 'nullable|image|max:5120', // Max 5MB
    ]);

    if (!$request->txid && !$request->binance_id) {
        return response()->json(['message' => 'Please provide either a TXID or a Binance ID.'], 422);
    }

    $proofPath = null;
    if ($request->hasFile('proof')) {
        $proofPath = $request->file('proof')->store('proofs', 'public');
    }

    $pending = \App\Models\PendingCryptoTransaction::create([
        'user_id'    => $request->user()->id,
        'currency'   => $request->currency,
        'amount'     => $request->amount,
        'txid'       => $request->txid,
        'binance_id' => $request->binance_id,
        'proof_path' => $proofPath,
        'status'     => 'pending',
    ]);

    // --- NEW: Alert Admin about manual payment proof ---
    try {
        $adminEmail = \App\Models\Setting::getValue('admin_notification_email', 'aliyantarar4@gmail.com');
        \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
            ->notify(new \App\Notifications\GenericDynamicNotification('admin_manual_payment', [
                'user' => ['email' => $request->user()->email],
                'payment' => ['reference' => $request->txid ?: $request->binance_id ?: 'N/A'],
                'admin_url' => url('/admin/billing/crypto'),
                'year' => date('Y')
            ]));
    } catch (\Exception $e) {
        \Log::error("Manual Payment Admin Alert Error: " . $e->getMessage());
    }
    // ----------------------------------------------------

    return response()->json(['message' => 'Transaction submitted for review.', 'data' => $pending]);
}

    /**
     * Admin: List pending crypto transactions.
     */
    public function adminPendingCrypto()
    {
        $pending = \App\Models\PendingCryptoTransaction::with('user:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json($pending);
    }

    /**
     * Admin: Approve or Reject a crypto transaction.
     */
    public function adminApproveCrypto(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string',
        ]);

        $pending = \App\Models\PendingCryptoTransaction::findOrFail($id);
        
        if ($pending->status !== 'pending') {
            return response()->json(['message' => 'Transaction already processed.'], 400);
        }

        DB::transaction(function () use ($pending, $request) {
            $pending->update([
                'status' => $request->status,
                'admin_note' => $request->admin_note,
            ]);

            if ($request->status === 'approved') {
                $user = User::lockForUpdate()->find($pending->user_id);
                $user->balance += $pending->amount;
                $user->save();

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'amount' => $pending->amount,
                    'reference' => "CRYPTO-" . ($pending->txid ?: $pending->binance_id ?: $pending->id),
                    'description' => "Crypto Top-up ({$pending->currency})",
                ]);

                // --- NEW: Trigger Payment Accepted Email ---
                try {
                    $user->notify(new \App\Notifications\PaymentAcceptedNotification([
                        'user' => ['name' => $user->name],
                        'payment' => ['reference' => $pending->txid ?: $pending->binance_id ?: $pending->id],
                        'action_url' => url('/app/billing'),
                        'year' => date('Y')
                    ]));
                } catch (\Exception $e) {
                    \Log::error("Payment Accepted Email Error: " . $e->getMessage());
                }
            } else {
                // Trigger Payment Rejected Email
                try {
                    $user = User::find($pending->user_id);
                    if ($user) {
                        $user->notify(new \App\Notifications\PaymentRejectedNotification([
                            'user' => ['name' => $user->name],
                            'payment' => ['reference' => $pending->txid ?: $pending->binance_id ?: $pending->id],
                            'reject_reason' => $request->admin_note ?: 'Proof could not be verified.',
                            'action_url' => url('/app/billing'),
                            'year' => date('Y')
                        ]));
                    }
                } catch (\Exception $e) {
                    \Log::error("Payment Rejected Email Error: " . $e->getMessage());
                }
            }
        });

        return response()->json(['message' => "Transaction {$request->status} successfully."]);
}

    /**
     * Create a Stripe Checkout Session in 'setup' mode to save a card.
     */
    public function createSetupIntent(Request $request)
    {
        Stripe::setApiKey(Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret'));

        $user = $request->user();
        
        if (!$user->stripe_customer_id) {
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name,
            ]);
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'setup',
            'customer' => $user->stripe_customer_id,
            'success_url' => url('/') . '/app/billing?setup_success=true',
            'cancel_url' => url('/') . '/app/billing?setup_canceled=true',
            'metadata' => [
                'user_id' => $user->id,
                'type' => 'card_setup'
            ]
        ]);

        return response()->json(['url' => $session->url]);
    }

    public function invoices(Request $request)
    {
        $userId = $request->user()->id;

        // Formal Invoices from Stripe
        $invoicesQuery = \App\Models\Invoice::where('user_id', $userId)->latest()->get();
        $stripeInvoiceIds = $invoicesQuery->pluck('stripe_invoice_id')->filter()->toArray();

        $formalInvoices = $invoicesQuery->map(function ($inv) {
            return [
                'id'             => (string) $inv->id,
                'invoice_number' => "INV-{$inv->id}",
                'amount'         => (float) $inv->amount,
                'amount_cents'   => (int) ($inv->amount * 100),
                'status'         => $inv->status,
                'gateway'        => $inv->stripe_invoice_id ? 'stripe' : 'manual',
                'period'         => $inv->created_at->format('M Y'),
                'created_at'     => $inv->created_at->toIso8601String(),
                'description'    => $inv->description ?: 'Subscription/Purchase',
                'pdf_url'        => $inv->pdf_url,
            ];
        });

        // Wallet Transactions (Top-ups, etc. that might not have formal Stripe invoices yet)
        $walletTx = WalletTransaction::where('user_id', $userId)
            ->whereNotIn('reference', $stripeInvoiceIds)
            ->latest()
            ->get()
            ->map(function ($t) {
                $gateway = 'balance';
                if (str_contains(strtolower($t->description), 'crypto')) $gateway = 'crypto';
                if (str_contains(strtolower($t->description), 'binance')) $gateway = 'binance';

                return [
                    'id'             => (string) $t->id,
                    'invoice_number' => "TX-{$t->id}",
                    'amount'         => (float) $t->amount,
                    'amount_cents'   => (int) ($t->amount * 100),
                    'status'         => 'paid',
                    'gateway'        => $gateway,
                    'period'         => $t->created_at->format('M Y'),
                    'created_at'     => $t->created_at->toIso8601String(),
                    'description'    => $t->description,
                    'pdf_url'        => null,
                ];
            });

        // Use collect() to avoid Eloquent Collection merge/sort errors with arrays
        return response()->json(collect($formalInvoices)->merge(collect($walletTx))->sortByDesc('created_at')->values());
    }

    /**
     * Admin: List all invoices/transactions for all users.
     */
    public function adminInvoices()
    {
        $transactions = WalletTransaction::with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json($transactions);
    }

    /**
     * Admin: Check status of payment gateways.
     */
    public function gatewayStatus(Request $request)
    {
        $refresh = $request->query('refresh') === 'true';
        $cacheKey = 'admin_gateway_status';

        try {
            if (!$refresh && Cache::has($cacheKey)) {
                return response()->json(Cache::get($cacheKey));
            }
        } catch (\Exception $e) {
            Log::error("Cache Error: " . $e->getMessage());
        }

        $stripeSecret = Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret');
        $stripePublishable = Setting::getValue('stripe_publishable_key') ?: config('services.stripe.publishable');
        $stripeWebhook = Setting::getValue('stripe_webhook_secret') ?: config('services.stripe.webhook_secret');

        $stripeStatus = 'not_configured';
        if ($stripeSecret && $stripePublishable) {
            try {
                Stripe::setApiKey($stripeSecret);
                // Simple call to verify key
                Account::retrieve();
                $stripeStatus = 'connected';
            } catch (\Exception $e) {
                Log::warning("Stripe Connection Error: " . $e->getMessage());
                $stripeStatus = 'error';
            }
        }

        $status = [
            'gateways' => [
                [
                    'id' => 'stripe',
                    'name' => 'Stripe',
                    'status' => $stripeStatus,
                    'webhook_health' => $stripeWebhook ? 'good' : 'missing',
                    'last_sync' => now()->toIso8601String(),
                ],
                [
                    'id' => 'paypal',
                    'name' => 'PayPal',
                    'status' => Setting::getValue('paypal_client_id') ? 'connected' : 'not_configured',
                    'webhook_health' => 'unknown',
                    'last_sync' => now()->toIso8601String(),
                ],
                [
                    'id' => 'cryptomus',
                    'name' => 'Cryptomus',
                    'status' => Setting::getValue('cryptomus_merchant_id') ? 'connected' : 'not_configured',
                    'webhook_health' => Setting::getValue('cryptomus_webhook_secret') ? 'good' : 'missing',
                    'last_sync' => now()->toIso8601String(),
                ],
                [
                    'id' => 'crypto',
                    'name' => 'Binance Pay (Manual)',
                    'status' => Setting::getValue('binance_pay_id') ? 'connected' : 'not_configured',
                    'webhook_health' => 'manual',
                    'last_sync' => now()->toIso8601String(),
                ]
            ]
        ];

        try {
            Cache::put($cacheKey, $status, 600);
        } catch (\Exception $e) {
            Log::error("Cache Put Error: " . $e->getMessage());
        }

        return response()->json($status);
    }

    public function publicGatewayStatus()
    {
        return response()->json([
            'stripe' => !empty(Setting::getValue('stripe_publishable_key')) && Setting::getValue('gateway_stripe_enabled') == '1',
            'paypal' => !empty(Setting::getValue('paypal_client_id')) && Setting::getValue('gateway_paypal_enabled') == '1',
            'cryptomus' => !empty(Setting::getValue('cryptomus_merchant_id')) && Setting::getValue('gateway_cryptomus_enabled') == '1',
            'crypto' => !empty(Setting::getValue('binance_pay_id')) && Setting::getValue('gateway_crypto_enabled') == '1',
        ]);
    }
    /**
     * Check and execute Auto Top-up for a user (Off-session Stripe Payment)
     */
    public function checkAndTriggerAutoTopUp(User $user)
    {
        // 1. Check global master switch
        if (Setting::getValue('auto_topup_enabled') !== '1') {
            return false;
        }

        $settings = $user->auto_topup_settings;
        
        // 2. Check user level toggle and data
        if (!isset($settings['enabled']) || !$settings['enabled'] || !isset($settings['amount']) || !$user->default_payment_method) {
            return false;
        }

        Log::info("Triggering Auto Top-up check for User #{$user->id}. Balance: {$user->balance}");

        // 3. Check Threshold
        $threshold = (float) ($settings['threshold'] ?? Setting::getValue('min_balance_threshold', 5));
        if ($user->balance >= $threshold) {
             return false;
        }

        // 4. Check Monthly Cap (Safety)
        $maxMonthly = (float) ($settings['max_monthly'] ?? Setting::getValue('max_monthly_topup', 500));
        $thisMonthCharges = WalletTransaction::where('user_id', $user->id)
            ->where('description', 'LIKE', '%Auto Top-up%')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
        
        if ($thisMonthCharges >= $maxMonthly) {
            Log::warning("Auto Top-up capped for user {$user->id}. Monthly limit reaches: {$thisMonthCharges} / {$maxMonthly}");
            return false;
        }

        $amount = (float) $settings['amount'];
        $amountWithVAT = $amount * 1.22;

        try {
            Log::info("Attempting off-session charge of \${$amount} for User #{$user->id}");
            Stripe::setApiKey(Setting::getValue('stripe_secret_key') ?: config('services.stripe.secret'));
            
            $pi = PaymentIntent::create([
                'amount' => round($amountWithVAT * 100),
                'currency' => 'usd',
                'customer' => $user->stripe_customer_id,
                'payment_method' => $user->default_payment_method,
                'off_session' => true,
                'confirm' => true,
                'description' => "Auto Top-up for User #{$user->id}",
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'auto_topup',
                    'original_amount' => $amount
                ]
            ]);

            if ($pi->status === 'succeeded') {
                $user->increment('balance', $amount);
                
                $referralService = app(ReferralService::class);
                $referralService->awardCommission($user, $amount, "Commission from Auto Top-up (Stripe)");

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'credit',
                    'description' => "Auto Top-up: Saved Card charged successfully.",
                    'reference' => $pi->id
                ]);
                Log::info("Auto Top-up successful for User #{$user->id}. Transaction: {$pi->id}");
                return true;
            }

            Log::error("Auto Top-up PI Status: " . $pi->status);
            return false;
        } catch (\Exception $e) {
            Log::error("Auto Top-up failed for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }
}
