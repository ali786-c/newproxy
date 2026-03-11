<?php

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$key = 'email_verification';
$template = EmailTemplate::where('key', $key)->first();

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

$content = '<h2 style="margin:0 0 16px; font-size:22px; color:#0f172a; text-align:center;">Verify Your Email Address</h2>
             <p style="margin:0 0 24px; font-size:15px; color:#334155; line-height:1.7; text-align:center;">Thank you for joining <strong>{{app.name}}</strong>! Please click the button below to verify your email address and complete your registration.</p>
             <div style="text-align:center; margin-bottom:30px;">
                <a href="{{verification_url}}" style="display:inline-block; background:#25935f; color:#ffffff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:700; font-size:16px; box-shadow:0 4px 12px rgba(37, 147, 95, 0.2);">Verify Email Address</a>
             </div>
             <p style="margin:0 0 20px; font-size:14px; color:#475569; line-height:1.6; text-align:center;">This link will expire in 60 minutes. If the button above doesn\'t work, copy and paste the following link into your browser:</p>
             <p style="word-break:break-all; font-size:12px; color:#25935f; text-align:center; background:#f8fafc; padding:12px; border-radius:6px;">{{verification_url}}</p>
             <div style="padding-top:20px; border-top:1px solid #e2e8f0; text-align:center; margin-top:30px;">
                <p style="margin:0; font-size:12px; color:#94a3b8;">If you did not request this verification, you can safely ignore this email.</p>
             </div>';

$body = str_replace('[[CONTENT]]', $content, $htmlBase);

EmailTemplate::updateOrCreate(
    ['key' => $key],
    [
        'name' => 'Email Verification Link',
        'subject' => 'Verify Your Email Address - {{app.name}}',
        'body' => $body,
        'format' => 'html',
        'variables' => ['verification_url', 'app.name', 'year'],
        'is_active' => true,
    ]
);

echo "Email template '{$key}' has been updated successfully.\n";
