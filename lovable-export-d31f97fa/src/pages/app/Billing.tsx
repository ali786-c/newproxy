import { useState, useMemo, useEffect } from "react";
import { SEOHead } from "@/components/seo/SEOHead";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import { clientApi, type Invoice, type Plan, type TopUpSettings } from "@/lib/api/dashboard";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { ManualCryptoDialog } from "@/components/shared/ManualCryptoDialog";
import { usePaymentConfig } from "@/contexts/PaymentConfigContext";
import { useCurrency } from "@/contexts/CurrencyContext";
import { useI18n } from "@/contexts/I18nContext";
import { useToast } from "@/hooks/use-toast";
import {
  CreditCard,
  Wallet,
  Bitcoin,
  Euro,
  AlertCircle,
  Info,
  RefreshCw,
  Check,
  Download,
  Loader2,
  Save,
  CreditCard as CardIcon
} from "lucide-react";

const MIN_PURCHASE_EUR = 0.01;

// Mock data removed. Using useQuery.

const STATUS_VARIANT: Record<string, "default" | "secondary" | "destructive"> = {
  paid: "default",
  pending: "secondary",
  failed: "destructive",
};


type PaymentMethod = "stripe" | "cryptomus" | "nowpayments" | "coinbase" | "manual";

export default function Billing() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { format, currency } = useCurrency();
  const { t } = useI18n();
  const [activeProduct] = useState("residential");
  const [amount, setAmount] = useState("50");
  const { gateways, autoTopUpEnabled } = usePaymentConfig();

  const [clientAutoTopUp, setClientAutoTopUp] = useState(false);
  const [topUpAmount, setTopUpAmount] = useState("50");
  const [minBalance, setMinBalance] = useState("5");
  const [maxMonthly, setMaxMonthly] = useState("500");

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [coupon, setCoupon] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showManualCrypto, setShowManualCrypto] = useState(false);
  const [appliedCoupon, setAppliedCoupon] = useState<{ code: string; discount: number; type: string; value: number } | null>(null);
  const [isValidatingCoupon, setIsValidatingCoupon] = useState(false);

  const { data: topUpSettings, isLoading: isLoadingSettings } = useQuery({
    queryKey: ["top-up-settings"],
    queryFn: () => clientApi.getTopUpSettings(),
  });

  const [showSuccessModal, setShowSuccessModal] = useState(false);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);

    if (params.get("success") === "true") {
      const sessionId = params.get("session_id");
      const gateway = params.get("gateway");

      // Clean URL immediately
      window.history.replaceState({}, document.title, window.location.pathname);

      if (sessionId) {
        // Call backend to verify and credit balance (guaranteed fulfillment)
        clientApi.verifySession(sessionId)
          .then(() => {
            queryClient.invalidateQueries({ queryKey: ["me"] });
            queryClient.invalidateQueries({ queryKey: ["invoices"] });
            queryClient.invalidateQueries({ queryKey: ["stats"] });
            setShowSuccessModal(true);
          })
          .catch((err) => {
            // Check if it's already processed or truly failed
            if (err.message && err.message.includes("already")) {
              queryClient.invalidateQueries({ queryKey: ["me"] });
              setShowSuccessModal(true);
            } else {
              // Truly failed verification - balance might still update via webhook soon
              toast({
                title: "Processing Payment",
                description: "Your payment was received. It may take a minute for your balance to update. Please refresh shortly.",
                variant: "default"
              });
              queryClient.invalidateQueries({ queryKey: ["me"] });
            }
          });
      } else {
        // No session_id (old flow), still refresh and show success
        queryClient.invalidateQueries({ queryKey: ["me"] });
        queryClient.invalidateQueries({ queryKey: ["invoices"] });
        setShowSuccessModal(true);
      }
    } else if (params.get("setup_success") === "true") {
      toast({ title: "Card Saved", description: "Your payment method has been securely saved for auto top-up." });
      window.history.replaceState({}, document.title, window.location.pathname);
    } else if (params.get("setup_canceled") === "true") {
      toast({ title: "Setup Canceled", description: "Card setup was canceled. Auto top-up remains disabled.", variant: "destructive" });
      window.history.replaceState({}, document.title, window.location.pathname);
    } else if (params.get("canceled") === "true") {
      toast({ title: "Payment Canceled", description: "Your payment was canceled. No charges were made.", variant: "destructive" });
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }, []);


  // Sync state with fetched data
  useMemo(() => {
    if (topUpSettings) {
      setClientAutoTopUp(topUpSettings.enabled);
      setTopUpAmount(topUpSettings.amount.toString());
      setMinBalance(topUpSettings.threshold.toString());
      setMaxMonthly(topUpSettings.max_monthly.toString());
    }
  }, [topUpSettings]);

  const saveSettingsMutation = useMutation({
    mutationFn: (data: { enabled: boolean; threshold: number; amount: number; max_monthly: number }) =>
      clientApi.updateTopUpSettings(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["top-up-settings"] });
      toast({ title: "Settings Saved", description: "Your auto top-up preferences have been updated." });
    },
    onError: (err: any) => toast({ title: "Error", description: err.message, variant: "destructive" }),
  });

  const { data: plans } = useQuery({
    queryKey: ["plans"],
    queryFn: () => clientApi.getPlans(),
  });

  const { data: invoices } = useQuery({
    queryKey: ["invoices"],
    queryFn: () => clientApi.getInvoices(),
  });

  const numAmount = parseFloat(amount) || 0;
  const discountAmount = appliedCoupon ? appliedCoupon.discount : 0;
  const netAmount = Math.max(0, numAmount - discountAmount);

  let currentVatRate = 0;
  if (gateways) {
    if (selectedMethod === "stripe") currentVatRate = gateways.stripe_vat / 100;
    else if (selectedMethod === "cryptomus") currentVatRate = gateways.cryptomus_vat / 100;
    else if (selectedMethod === "nowpayments") currentVatRate = (gateways.nowpayments_vat || 0) / 100;
    else if (selectedMethod === "coinbase") currentVatRate = (gateways.coinbase_vat || 0) / 100;
    else if (selectedMethod === "manual") currentVatRate = gateways.manual_vat / 100;
  }

  const vatAmount = netAmount * currentVatRate;
  const totalAmount = netAmount + vatAmount;
  const isVatFree = currentVatRate === 0;

  // Convert a EUR amount to the user's display currency
  // exchange_rate is now EUR-based: 1 EUR = exchange_rate units of currency
  const eurToDisplay = (eurAmt: number) => currency.exchange_rate * eurAmt;
  const displaySymbol = currency.symbol;

  // Minimum purchase in user's display currency
  const minPurchaseDisplay = eurToDisplay(MIN_PURCHASE_EUR);
  const belowMinimum = numAmount < minPurchaseDisplay;

  const handleApplyCoupon = async () => {
    if (!coupon.trim()) return;
    if (numAmount < MIN_PURCHASE_EUR) {
      toast({ title: "Error", description: `Please enter an amount of at least ${format(MIN_PURCHASE_EUR)} first.`, variant: "destructive" });
      return;
    }

    setIsValidatingCoupon(true);
    try {
      const res = await clientApi.validateCoupon(coupon.toUpperCase(), numAmount);
      if (res.valid) {
        setAppliedCoupon({
          code: res.code,
          discount: res.discount,
          type: res.type,
          value: res.value
        });
        toast({ title: "Coupon Applied", description: `You saved ${format(res.discount)}!` });
      }
    } catch (err: any) {
      setAppliedCoupon(null);
      toast({ title: "Invalid Coupon", description: err.message || "This code is not valid.", variant: "destructive" });
    } finally {
      setIsValidatingCoupon(false);
    }
  };

  const selectPlan = (planId: string) => {
    toast({ title: "Product Selected", description: `Switching to ${planId}.` });
  };


  const handleAutoTopUp = async (enabled: boolean) => {
    setClientAutoTopUp(enabled);
    if (enabled && topUpSettings && !topUpSettings.has_payment_method) {
      try {
        const { url } = await clientApi.createSetupIntent();
        window.location.href = url;
      } catch (err: any) {
        toast({ title: "Setup Error", description: err.message, variant: "destructive" });
        setClientAutoTopUp(false);
      }
    } else {
      // If turning off or if card exists, just save the status
      saveSettingsMutation.mutate({
        enabled,
        threshold: parseFloat(minBalance),
        amount: parseFloat(topUpAmount),
        max_monthly: parseFloat(maxMonthly)
      });
    }
  };

  const handleSavePreferences = () => {
    saveSettingsMutation.mutate({
      enabled: clientAutoTopUp,
      threshold: parseFloat(minBalance),
      amount: parseFloat(topUpAmount),
      max_monthly: parseFloat(maxMonthly)
    });
  };

  const handleCheckout = async () => {
    if (belowMinimum) {
      toast({ title: "Minimum not met", description: `Minimum purchase is ${displaySymbol}${minPurchaseDisplay.toFixed(2)}.`, variant: "destructive" });
      return;
    }
    if (!selectedMethod) {
      toast({ title: "Select a payment method", description: "Please choose how you'd like to pay.", variant: "destructive" });
      return;
    }

    if (selectedMethod === "cryptomus") {
      setIsSubmitting(true);
      try {
        const { url } = await clientApi.createCryptomusCheckout(numAmount, appliedCoupon?.code);
        window.location.href = url;
      } catch (err: any) {
        toast({ title: "Checkout Error", description: err.message, variant: "destructive" });
      } finally {
        setIsSubmitting(false);
      }
    } else if (selectedMethod === "nowpayments") {
      setIsSubmitting(true);
      try {
        const { url } = await clientApi.createNowPaymentsCheckout(numAmount, appliedCoupon?.code);
        window.location.href = url;
      } catch (err: any) {
        toast({ title: "Checkout Error", description: err.message, variant: "destructive" });
      } finally {
        setIsSubmitting(false);
      }
    } else if (selectedMethod === "coinbase") {
      setIsSubmitting(true);
      try {
        const { url } = await clientApi.createCoinbaseCheckout(numAmount, appliedCoupon?.code);
        window.location.href = url;
      } catch (err: any) {
        toast({ title: "Checkout Error", description: err.message, variant: "destructive" });
      } finally {
        setIsSubmitting(false);
      }
    } else if (selectedMethod === "stripe") {
      setIsSubmitting(true);
      try {
        // totalAmount and netAmount are in user's display currency
        // currency.code tells backend which currency to convert from
        // Backend divides by exchange_rate to get EUR for Stripe
        const { url } = await clientApi.createCheckout(totalAmount, netAmount, currency.code);
        window.location.href = url;
      } catch (err: any) {
        toast({ title: "Checkout Error", description: err.message, variant: "destructive" });
      } finally {
        setIsSubmitting(false);
      }
    } else if (selectedMethod === "manual") {
      setShowManualCrypto(true);
    } else {
      toast({ title: `Pay with ${selectedMethod}`, description: `Redirecting to ${selectedMethod} checkout for ${format(totalAmount)}...` });
    }
  };

  const copyAddress = (addr: string) => {
    navigator.clipboard.writeText(addr);
    toast({ title: "Copied!", description: "Wallet address copied to clipboard." });
  };

  const PAYMENT_METHODS = [
    { id: "stripe" as PaymentMethod, name: "Card (Stripe)", subtitle: "Credit/Debit Card", icon: CreditCard, vatLabel: gateways.stripe_vat > 0 ? `+${gateways.stripe_vat}% Fee` : "No Fee", enabled: gateways.stripe },
    { id: "nowpayments" as PaymentMethod, name: "Crypto (NP)", subtitle: "Bitcoin/Altcoins", icon: Bitcoin, vatLabel: gateways.nowpayments_vat > 0 ? `+${gateways.nowpayments_vat}% Fee` : "No Fee", enabled: gateways.nowpayments },
    { id: "coinbase" as PaymentMethod, name: "Crypto (CB)", subtitle: "Coinbase Commerce", icon: Bitcoin, vatLabel: gateways.coinbase_vat > 0 ? `+${gateways.coinbase_vat}% Fee` : "No Fee", enabled: gateways.coinbase },
    { id: "cryptomus" as PaymentMethod, name: "Crypto (CM)", subtitle: "Automated via Cryptomus", icon: Bitcoin, vatLabel: gateways.cryptomus_vat > 0 ? `+${gateways.cryptomus_vat}% Fee` : "No Fee", enabled: gateways.cryptomus },
    { id: "manual" as PaymentMethod, name: "Binance Pay", subtitle: "Manual Transfer", icon: Bitcoin, vatLabel: gateways.manual_vat > 0 ? `+${gateways.manual_vat}% Fee` : "No Fee", enabled: gateways.crypto },
  ];

  const anyGatewayEnabled = PAYMENT_METHODS.length > 0;

  return (
    <>
      <SEOHead title="Account & Billing" noindex />
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">Account & Billing</h1>

        {/* Add Balance / Payment Methods */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-lg">
              <Euro className="h-5 w-5" /> {t("billing.topUp")}
            </CardTitle>
            <CardDescription>{t("billing.minPurchaseTitle")}: {displaySymbol}{minPurchaseDisplay.toFixed(2)}. Fees may apply depending on the selected payment method.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            {/* Amount input */}
            <div className="grid gap-4 sm:grid-cols-2 max-w-lg">
              <div className="space-y-1.5">
                <Label>Amount</Label>
                <Input
                  type="number"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  min={minPurchaseDisplay}
                  step="0.01"
                  placeholder={`Min ${displaySymbol}${minPurchaseDisplay.toFixed(2)}`}
                  className={belowMinimum && numAmount > 0 ? "border-destructive ring-destructive" : ""}
                />
                {belowMinimum && numAmount > 0 && (
                  <p className="text-sm text-destructive font-medium flex items-center gap-1.5 mt-1.5 p-2 bg-destructive/10 rounded-md">
                    <AlertCircle className="h-4 w-4" />
                    Amount must be greater than 0
                  </p>
                )}
              </div>
              <div className="space-y-1.5">
                <Label>Promo Code (optional)</Label>
                <div className="flex gap-2">
                  <Input
                    placeholder="WELCOME20"
                    value={coupon}
                    onChange={(e) => setCoupon(e.target.value)}
                    className={appliedCoupon ? "border-green-500 bg-green-500/5" : ""}
                    disabled={!!appliedCoupon}
                  />
                  {appliedCoupon ? (
                    <Button variant="outline" size="sm" onClick={() => { setAppliedCoupon(null); setCoupon(""); }}>Remove</Button>
                  ) : (
                    <Button
                      variant="outline"
                      size="sm"
                      className="shrink-0"
                      onClick={handleApplyCoupon}
                      disabled={isValidatingCoupon || !coupon.trim()}
                    >
                      {isValidatingCoupon ? <Loader2 className="h-4 w-4 animate-spin" /> : "Apply"}
                    </Button>
                  )}
                </div>
                {appliedCoupon && (
                  <p className="text-[11px] text-green-600 font-medium"> Coupon applied: {appliedCoupon.type === 'percentage' ? `${appliedCoupon.value}%` : `${format(appliedCoupon.value)}`} off</p>
                )}
              </div>
            </div>

            {/* Payment method selection */}
            {!anyGatewayEnabled && (
              <p className="text-sm text-muted-foreground italic">No payment methods are currently available. Please contact support.</p>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
              {PAYMENT_METHODS.map((pm) => {
                const enabled = pm.enabled;
                const isSelected = selectedMethod === pm.id;
                return (
                  <Button
                    key={pm.id}
                    variant={isSelected ? "default" : "outline"}
                    className={`h-auto py-4 flex-col gap-1 relative ${!enabled ? "opacity-40 cursor-not-allowed" : ""}`}
                    disabled={!enabled}
                    onClick={() => setSelectedMethod(pm.id)}
                  >
                    <pm.icon className="h-6 w-6" />
                    <span className="font-semibold">{pm.name}</span>
                    <span className="text-[11px] font-normal opacity-80">{pm.subtitle}</span>
                    <Badge variant={pm.id === "crypto" || pm.id === "manual" ? "default" : "secondary"} className="text-[10px] mt-1">
                      {pm.vatLabel}
                    </Badge>
                    {!enabled && <span className="text-[10px] font-normal">Not available</span>}
                  </Button>
                );
              })}
            </div>

            {/* Order summary */}
            {selectedMethod && numAmount >= MIN_PURCHASE_EUR && (
              <div className="rounded-lg border bg-muted/30 p-4 max-w-sm space-y-2">
                <h4 className="text-sm font-semibold">{t("billing.orderSummary")}</h4>
                <div className="flex justify-between text-sm">
                  <span>{t("billing.subtotal")}</span>
                  <span>{displaySymbol}{netAmount.toFixed(2)}</span>
                </div>
                {discountAmount > 0 && (
                  <div className="flex justify-between text-sm text-green-600 font-medium font-mono">
                    <span>Discount ({appliedCoupon?.code})</span>
                    <span>-{displaySymbol}{discountAmount.toFixed(2)}</span>
                  </div>
                )}
                <div className="flex justify-between text-sm">
                  <span className="flex items-center gap-1">
                    Fee{currentVatRate > 0 ? ` (${(currentVatRate * 100).toFixed(0)}%)` : ""}
                    {isVatFree && <Info className="h-3 w-3 text-muted-foreground" />}
                  </span>
                  <span className={isVatFree ? "line-through text-muted-foreground" : ""}>
                    {isVatFree ? `${displaySymbol}0.00 — exempt` : `${displaySymbol}${vatAmount.toFixed(2)}`}
                  </span>
                </div>
                <Separator />
                <div className="flex justify-between text-sm font-bold">
                  <span>{t("common.total")}</span>
                  <span>{displaySymbol}{totalAmount.toFixed(2)}</span>
                </div>
                <Button className="w-full mt-2" onClick={handleCheckout} disabled={belowMinimum || isSubmitting}>
                  {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                  {t("common.pay")} {displaySymbol}{totalAmount.toFixed(2)}
                </Button>
              </div>
            )}

          </CardContent>
        </Card>

        {/* Auto Top-Up */}
        {autoTopUpEnabled && (
          <Card className={!topUpSettings?.global_enabled ? "opacity-60 grayscale-[0.5]" : ""}>
            <CardHeader>
              <CardTitle className="flex items-center justify-between text-lg">
                <div className="flex items-center gap-2">
                  <RefreshCw className="h-5 w-5" /> Auto Top-Up
                </div>
                {topUpSettings?.has_payment_method ? (
                  <Badge variant="secondary" className="bg-green-100 text-green-700 hover:bg-green-100 flex gap-1 items-center">
                    <Check className="h-3 w-3" /> Card Saved
                  </Badge>
                ) : (
                  <Badge variant="outline" className="text-muted-foreground flex gap-1 items-center">
                    <AlertCircle className="h-3 w-3" /> No Card Saved
                  </Badge>
                )}
              </CardTitle>
              {!topUpSettings?.global_enabled && (
                <CardDescription className="text-destructive font-medium">
                  Auto Top-up is currently disabled by the administrator.
                </CardDescription>
              )}
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">Enable Auto Top-Up</p>
                  <p className="text-xs text-muted-foreground">Automatically add balance when it drops below the threshold</p>
                </div>
                <Switch
                  checked={clientAutoTopUp}
                  onCheckedChange={handleAutoTopUp}
                  disabled={!topUpSettings?.global_enabled}
                />
              </div>
              {clientAutoTopUp && (
                <>
                  <div className="grid gap-4 sm:grid-cols-3">
                    <div className="space-y-1.5">
                      <Label>Min Balance Threshold</Label>
                      <Input type="number" value={minBalance} onChange={(e) => setMinBalance(e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Top-Up Amount</Label>
                      <Input type="number" value={topUpAmount} onChange={(e) => setTopUpAmount(e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Monthly Max Cap</Label>
                      <Input type="number" value={maxMonthly} onChange={(e) => setMaxMonthly(e.target.value)} />
                    </div>
                  </div>
                  <div className="flex justify-between items-center pt-2">
                    <p className="text-[11px] text-muted-foreground max-w-[250px]">
                      A {gateways.stripe_vat}% Stripe Fee will be added to each auto top-up.
                      Total charge: {format(parseFloat(topUpAmount) * (1 + (gateways.stripe_vat / 100)))}
                    </p>
                    <Button
                      size="sm"
                      onClick={handleSavePreferences}
                      disabled={saveSettingsMutation.isPending}
                    >
                      {saveSettingsMutation.isPending ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <Save className="h-3 w-3 mr-1" />}
                      Save Preferences
                    </Button>
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        )}

        {/* Product Selection */}
        <div className="grid gap-4 sm:grid-cols-3">
          {(plans || []).map((plan) => {
            const isCurrent = plan.id === activeProduct;
            return (
              <Card key={plan.id} className={isCurrent ? "border-primary ring-2 ring-primary/20" : ""}>
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center justify-between text-lg">
                    {plan.name}
                    {isCurrent && <Badge>Active</Badge>}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-3xl font-bold">
                    {format(plan.price_cents / 100)}
                    <span className="text-base font-normal text-muted-foreground">
                      /{plan.id === "datacenter" ? "GB" : "GB"}
                    </span>
                  </p>
                  <ul className="space-y-1.5">
                    {(plan.features || []).map((f) => (
                      <li key={f} className="flex items-start gap-2 text-sm text-muted-foreground">
                        <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        {f}
                      </li>
                    ))}
                  </ul>
                  <div className="grid gap-2">
                    <Button
                      variant={isCurrent ? "outline" : "default"}
                      className="w-full"
                      disabled={isCurrent}
                      onClick={() => selectPlan(plan.id)}
                    >
                      {isCurrent ? "Active Product" : "Select Product"}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        {/* Invoices */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">{t("nav.invoices")}</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Period</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Date</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(invoices || []).map((inv) => (
                  <TableRow key={inv.id}>
                    <TableCell className="font-medium">{inv.period}</TableCell>
                    <TableCell>{format(inv.amount_cents / 100)}</TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[inv.status] ?? "secondary"}>{inv.status}</Badge>
                    </TableCell>
                    <TableCell className="text-right text-sm text-muted-foreground space-x-2">
                      <span>{new Date(inv.created_at).toLocaleDateString()}</span>
                      {inv.pdf_url && (
                        <Button variant="ghost" size="icon" className="h-7 w-7" asChild title="Download PDF">
                          <a href={inv.pdf_url} target="_blank" rel="noopener noreferrer">
                            <Download className="h-3.5 w-3.5" />
                          </a>
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>

      <ManualCryptoDialog
        open={showManualCrypto}
        onOpenChange={setShowManualCrypto}
        defaultAmount={amount}
      />
    </>
  );
}
