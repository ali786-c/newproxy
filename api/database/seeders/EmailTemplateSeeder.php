<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $htmlBase = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Email</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f9; font-family: Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9; padding:28px 14px;">
    <tr>
      <td align="center">
        <!-- Main container -->
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0"
               style="width:640px; max-width:640px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 10px 34px rgba(0,0,0,0.07);">
          <!-- ===== HEADER START ===== -->
          <tr>
            <td style="background:#25935f; padding:22px 26px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td align="left" style="vertical-align:middle;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="vertical-align:middle; padding-right:10px;">
                          <svg width="30" height="30" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-label="UpgradedProxy" role="img" style="display:block;">
                            <path fill="#ffffff" d="M12 18l20-10 20 10-20 10-20-10z"/>
                            <path fill="#ffffff" opacity="0.92" d="M12 30l20-10 20 10-20 10-20-10z"/>
                            <path fill="#ffffff" opacity="0.85" d="M12 42l20-10 20 10-20 10-20-10z"/>
                          </svg>
                        </td>
                        <td style="vertical-align:middle;">
                          <div style="color:#ffffff; font-size:18px; font-weight:800; letter-spacing:0.2px; line-height:1;">
                            {{app.name}}
                          </div>
                          <div style="color:#e6fff5; font-size:12px; margin-top:4px; letter-spacing:0.3px;">
                            Secure • Fast • Reliable
                          </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td align="right" style="vertical-align:middle;">
                    <div style="color:#e6fff5; font-size:12px; line-height:1.4; text-align:right;">
                      Need help? <a href="mailto:support@upgraderproxy.com" style="color:#ffffff; text-decoration:underline;">support@upgraderproxy.com</a>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- ===== HEADER END ===== -->
          <!-- ===== CONTENT AREA ===== -->
          <tr>
            <td style="padding:36px 28px;">
                [[CONTENT]]
            </td>
          </tr>
          <!-- ===== FOOTER START ===== -->
          <tr>
            <td style="background:#0b1220; padding:26px 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td align="left" style="vertical-align:top;">
                    <div style="color:#ffffff; font-size:14px; font-weight:800; letter-spacing:0.2px;">
                      {{app.name}}
                    </div>
                    <div style="color:#a7b0c0; font-size:12px; line-height:1.7; margin-top:6px;">
                      Premium proxy infrastructure built for scale, speed & reliability.
                    </div>
                  </td>
                  <td align="right" style="vertical-align:top;">
                    <div style="font-size:12px; line-height:1.9;">
                      <a href="{{app.url}}" style="color:#5eead4; text-decoration:none;">Website</a><br/>
                      <a href="{{app.url}}/login" style="color:#5eead4; text-decoration:none;">Login</a><br/>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" style="padding-top:16px; border-top:1px solid rgba(255,255,255,0.08);">
                    <div style="color:#7f8aa3; font-size:11px; line-height:1.7;">
                      © {{year}} {{app.name}}. All rights reserved.
                      &nbsp;•&nbsp;
                      <a href="{{app.url}}/unsubscribe" style="color:#7f8aa3; text-decoration:underline;">Unsubscribe</a>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- ===== FOOTER END ===== -->
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $templates = [
            [
                'key' => 'welcome_user',
                'name' => 'User Welcome Email',
                'subject' => 'Welcome to {{app.name}}!',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Welcome, {{user.name}}!</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">We are excited to have you on board. Your account has been successfully created and you can now explore our premium proxy network.</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">Go to Dashboard</a>',
                'variables' => ['user.name', 'app.name', 'action_url', 'year'],
            ],
            [
                'key' => 'reset_password_user',
                'name' => 'Password Reset Request',
                'subject' => 'Reset Your Password - {{app.name}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Password Reset</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, you are receiving this email because we received a password reset request for your account.</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">Reset Password</a>
                             <p style="margin:20px 0 0; font-size:12px; color:#64748b;">This password reset link will expire in 60 minutes.</p>',
                'variables' => ['user.name', 'app.name', 'action_url', 'year'],
            ],
            [
                'key' => 'password_changed_user',
                'name' => 'Password Changed Confirmation',
                'subject' => 'Your password has been changed',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Security Alert</h2>
                             <p style="margin:0; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, this is a confirmation that your password for {{app.name}} has been successfully changed.<br><br>If you did not perform this action, please contact support immediately.</p>',
                'variables' => ['user.name', 'app.name', 'year'],
            ],
            [
                'key' => 'order_confirmation_user',
                'name' => 'Order Confirmation',
                'subject' => 'Order Confirmation #{{order.id}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Thank you for your order!</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, your order #{{order.id}} for <strong>{{order.amount}}</strong> has been processed successfully.</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Order Details</a>',
                'variables' => ['user.name', 'order.id', 'order.amount', 'app.name', 'action_url', 'year'],
            ],
            [
                'key' => 'support_ticket_reply_user',
                'name' => 'Support Ticket Reply',
                'subject' => 'Re: [Ticket #{{ticket.id}}] {{ticket.subject}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Support Reply</h2>
                             <p style="margin:0 0 16px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, a member of our support team has replied to your ticket #{{ticket.id}}.</p>
                             <div style="background:#f8fafc; padding:20px; border-radius:8px; border-left:4px solid #25935f; margin-bottom:20px;">
                                <p style="margin:0; font-size:14px; color:#334155; font-style:italic;">"{{reply_content}}"</p>
                             </div>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Ticket</a>',
                'variables' => ['user.name', 'ticket.id', 'ticket.subject', 'reply_content', 'action_url', 'year'],
            ],
            [
                'key' => 'support_ticket_opened_user',
                'name' => 'Support Ticket Opened',
                'subject' => 'Ticket Opened: {{ticket.subject}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Ticket Created</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, Your support ticket #{{ticket.id}} has been successfully opened. Our team will review it and get back to you soon.</p>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155;"><strong>Subject:</strong> {{ticket.subject}}</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View My Ticket</a>',
                'variables' => ['user.name', 'ticket.id', 'ticket.subject', 'action_url', 'year'],
            ],
            [
                'key' => 'manual_payment_accepted_user',
                'name' => 'Manual Payment Accepted',
                'subject' => 'Payment Confirmed: {{payment.reference}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Payment Accepted</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, Good news! Your manual payment proof for reference <strong>{{payment.reference}}</strong> has been accepted. Your balance has been updated.</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View My Invoices</a>',
                'variables' => ['user.name', 'payment.reference', 'action_url', 'year'],
            ],
            [
                'key' => 'manual_payment_rejected_user',
                'name' => 'Manual Payment Rejected',
                'subject' => 'Payment Status Update: {{payment.reference}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#e11d48;">Payment Rejected</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, Unfortunately, we were unable to verify your manual payment proof for reference <strong>{{payment.reference}}</strong>.</p>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155;"><strong>Reason:</strong> {{reject_reason}}</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">Try Again / View Tickets</a>',
                'variables' => ['user.name', 'payment.reference', 'reject_reason', 'action_url', 'year'],
            ],
            [
                'key' => 'proxy_created_user',
                'name' => 'Proxy Subscription Active',
                'subject' => 'Proxy Created: {{product.name}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Proxy Ready</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, Your proxy for <strong>{{product.name}}</strong> is now active and ready to use!</p>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155;"><strong>Order ID:</strong> #{{order.id}}</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">Manage My Proxies</a>',
                'variables' => ['user.name', 'product.name', 'order.id', 'action_url', 'year'],
            ],
            // ADMIN TEMPLATES
            [
                'key' => 'admin_new_user',
                'name' => 'Admin: New User Registration',
                'subject' => '[Admin] New User Registered: {{user.email}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">New User Alert</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">A new user has just registered on {{app.name}}.</p>
                             <ul style="margin:0 0 20px; padding:0; list-style:none; font-size:14px; color:#334155;">
                                <li><strong>Name:</strong> {{user.name}}</li>
                                <li><strong>Email:</strong> {{user.email}}</li>
                                <li><strong>IP:</strong> {{user.ip}}</li>
                             </ul>
                             <a href="{{admin_url}}" style="display:inline-block; background:#0f172a; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View User in Admin</a>',
                'variables' => ['user.name', 'user.email', 'user.ip', 'app.name', 'admin_url', 'year'],
            ],
            [
                'key' => 'admin_new_order',
                'name' => 'Admin: New Order Received',
                'subject' => '[Admin] New Order Alert! #{{order.id}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">New Order Alert</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">A new order has been placed.</p>
                             <ul style="margin:0 0 20px; padding:0; list-style:none; font-size:14px; color:#334155;">
                                <li><strong>User:</strong> {{user.email}}</li>
                                <li><strong>Amount:</strong> {{order.amount}}</li>
                                <li><strong>Order ID:</strong> {{order.id}}</li>
                             </ul>
                             <a href="{{admin_url}}" style="display:inline-block; background:#0f172a; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Order</a>',
                'variables' => ['user.email', 'order.id', 'order.amount', 'admin_url', 'year'],
            ],
            [
                'key' => 'admin_manual_payment',
                'name' => 'Admin: Manual Payment Submitted',
                'subject' => '[Admin] Manual Payment Proof Submitted',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Manual Payment Proof</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">A user has submitted proof for a manual payment.</p>
                             <ul style="margin:0 0 20px; padding:0; list-style:none; font-size:14px; color:#334155;">
                                <li><strong>User:</strong> {{user.email}}</li>
                                <li><strong>Reference:</strong> {{payment.reference}}</li>
                             </ul>
                             <a href="{{admin_url}}" style="display:inline-block; background:#0f172a; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">Verify Payment</a>',
                'variables' => ['user.email', 'payment.reference', 'admin_url', 'year'],
            ],
            [
                'key' => 'admin_new_ticket',
                'name' => 'Admin: New Support Ticket',
                'subject' => '[Admin] New Support Ticket: {{ticket.subject}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">New Ticket Alert</h2>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155; line-height:1.7;">A new support ticket has been opened.</p>
                             <ul style="margin:0 0 20px; padding:0; list-style:none; font-size:14px; color:#334155;">
                                <li><strong>User:</strong> {{user.email}}</li>
                                <li><strong>Subject:</strong> {{ticket.subject}}</li>
                                <li><strong>Priority:</strong> {{ticket.priority}}</li>
                             </ul>
                             <a href="{{admin_url}}" style="display:inline-block; background:#0f172a; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Ticket</a>',
                'variables' => ['user.email', 'ticket.subject', 'ticket.priority', 'admin_url', 'year'],
            ],
            [
                'key' => 'invoice_status_updated',
                'name' => 'Financial Record Status Updated',
                'subject' => 'Update on your Financial Record ({{record.id}})',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Status Updated</h2>
                             <p style="margin:0 0 16px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, the status of your financial record <strong>{{record.id}}</strong> ({{record.description}}) has been updated by an administrator.</p>
                             <div style="background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                                <p style="margin:0 0 8px; font-size:14px;"><strong>Old Status:</strong> {{record.old_status}}</p>
                                <p style="margin:0 0 8px; font-size:14px;"><strong>New Status:</strong> <span style="color:#25935f; font-weight:700;">{{record.new_status}}</span></p>
                                <p style="margin:0; font-size:14px;"><strong>Amount:</strong> {{record.amount}}</p>
                             </div>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Billing History</a>',
                'variables' => ['user.name', 'record.id', 'record.description', 'record.old_status', 'record.new_status', 'record.amount', 'action_url', 'year'],
            ],
            [
                'key' => 'influencer_rate_updated',
                'name' => 'Influencer Rate Updated',
                'subject' => 'Your custom referral rate has been updated',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Commission Rate Updated</h2>
                             <p style="margin:0 0 16px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, your custom commission rate for the referral program has been updated.</p>
                             <div style="background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                                <p style="margin:0 0 8px; font-size:14px;"><strong>Previous Rate:</strong> {{old_rate}}</p>
                                <p style="margin:0; font-size:14px;"><strong>New Rate:</strong> <span style="color:#25935f; font-weight:700;">{{new_rate}}</span></p>
                             </div>
                             <p style="margin:0 0 20px; font-size:14px; color:#334155;">This rate applies to all new eligible commissions earned from your referrals.</p>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Referral Panel</a>',
                'variables' => ['user.name', 'old_rate', 'new_rate', 'action_url', 'year'],
            ],
            [
                'key' => 'referral_commission_released',
                'name' => 'Referral Commission Released',
                'subject' => 'Referral Commission Released: {{amount}}',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#0f172a;">Funds Released</h2>
                             <p style="margin:0 0 16px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, a referral commission of <strong>{{amount}}</strong> has been released to your wallet balance.</p>
                             <div style="background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #e2e8f0;">
                                <p style="margin:0 0 4px; font-size:14px;"><strong>Amount:</strong> {{amount}}</p>
                                <p style="margin:0; font-size:14px;"><strong>Description:</strong> {{description}}</p>
                             </div>
                             <a href="{{action_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">View Wallet Balance</a>',
                'variables' => ['user.name', 'amount', 'description', 'action_url', 'year'],
            ],
            [
                'key' => 'referral_commission_voided',
                'name' => 'Referral Commission Voided',
                'subject' => 'Update on Referral Commission ({{amount}})',
                'content' => '<h2 style="margin:0 0 16px; font-size:20px; color:#e11d48;">Commission Voided</h2>
                             <p style="margin:0 0 16px; font-size:14px; color:#334155; line-height:1.7;">Hello {{user.name}}, we are writing to inform you that a pending referral commission of <strong>{{amount}}</strong> has been voided.</p>
                             <div style="background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #e11d4833;">
                                <p style="margin:0 0 4px; font-size:14px;"><strong>Amount:</strong> {{amount}}</p>
                                <p style="margin:0; font-size:14px;"><strong>Description:</strong> {{description}}</p>
                                <p style="margin:8px 0 0; font-size:14px;"><strong>Status:</strong> {{status}}</p>
                             </div>
                             <p style="margin:0; font-size:14px; color:#64748b;">If you believe this is an error, please contact support.</p>',
                'variables' => ['user.name', 'amount', 'description', 'status', 'year'],
            ],
            [
                'key' => 'email_verification',
                'name' => 'Email Verification Code',
                'subject' => '{{verification_code}} is your verification code',
                'content' => '<h2 style="margin:0 0 16px; font-size:22px; color:#0f172a; text-align:center;">Verify Your Email Address</h2>
                             <p style="margin:0 0 24px; font-size:15px; color:#334155; line-height:1.7; text-align:center;">Thank you for joining <strong>{{app.name}}</strong>! Please use the following 6-digit code to complete your registration and verify your account.</p>
                             <div style="background:#f0fdf4; padding:32px; border-radius:16px; text-align:center; border:2px dashed #25935f; margin-bottom:24px;">
                                <div style="font-size:36px; font-weight:900; letter-spacing:10px; color:#25935f; margin-bottom:8px;">{{verification_code}}</div>
                                <div style="font-size:12px; color:#166534; font-weight:600; text-transform:uppercase; letter-spacing:1px;">Expires in 15 minutes</div>
                             </div>
                             <p style="margin:0 0 20px; font-size:14px; color:#475569; line-height:1.6; text-align:center;">Simply enter this code on the verification screen to proceed.</p>
                             <div style="padding-top:20px; border-top:1px solid #e2e8f0; text-align:center;">
                                <p style="margin:0; font-size:12px; color:#94a3b8;">If you did not request this verification, you can safely ignore this email.</p>
                             </div>',
                'variables' => ['verification_code', 'app.name', 'year'],
            ],
        ];

        foreach ($templates as $t) {
            $body = str_replace('[[CONTENT]]', $t['content'], $htmlBase);
            \App\Models\EmailTemplate::updateOrCreate(
                ['key' => $t['key']],
                [
                    'name' => $t['name'],
                    'subject' => $t['subject'],
                    'body' => $body,
                    'format' => 'html',
                    'variables' => $t['variables'],
                ]
            );
        }
    }
}
