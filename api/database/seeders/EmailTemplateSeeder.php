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
        $templates = [
            [
                'key' => 'welcome_user',
                'name' => 'User Welcome Email',
                'subject' => 'Welcome to {{app.name}}!',
                'body' => "# Welcome, {{user.name}}!\n\nWe are excited to have you on board. Your account has been successfully created.\n\n[Go to Dashboard]({{action_url}})\n\nIf you have any questions, feel free to reply to this email.\n\nBest regards,\nThe {{app.name}} Team",
                'format' => 'markdown',
                'variables' => ['user.name', 'app.name', 'action_url'],
            ],
            [
                'key' => 'reset_password_user',
                'name' => 'Password Reset Request',
                'subject' => 'Reset Your Password - {{app.name}}',
                'body' => "# Password Reset\n\nHello {{user.name}},\n\nYou are receiving this email because we received a password reset request for your account.\n\n[Reset Password]({{action_url}})\n\nThis password reset link will expire in 60 minutes.\n\nIf you did not request a password reset, no further action is required.",
                'format' => 'markdown',
                'variables' => ['user.name', 'app.name', 'action_url'],
            ],
            [
                'key' => 'password_changed_user',
                'name' => 'Password Changed Confirmation',
                'subject' => 'Your password has been changed',
                'body' => "Hello {{user.name}},\n\nThis is a confirmation that your password for {{app.name}} has been successfully changed.\n\nIf you did not perform this action, please contact support immediately.",
                'format' => 'markdown',
                'variables' => ['user.name', 'app.name'],
            ],
            [
                'key' => 'order_confirmation_user',
                'name' => 'Order Confirmation',
                'subject' => 'Order Confirmation #{{order.id}}',
                'body' => "# Thank you for your order!\n\nHello {{user.name}},\n\nYour order #{{order.id}} for **{{order.amount}}** has been processed successfully.\n\n[View Order Details]({{action_url}})",
                'format' => 'markdown',
                'variables' => ['user.name', 'order.id', 'order.amount', 'app.name', 'action_url'],
            ],
            [
                'key' => 'support_ticket_reply_user',
                'name' => 'Support Ticket Reply',
                'subject' => 'Re: [Ticket #{{ticket.id}}] {{ticket.subject}}',
                'body' => "Hello {{user.name}},\n\nA member of our support team has replied to your ticket #{{ticket.id}}.\n\n**Reply:**\n{{reply_content}}\n\n[View Ticket]({{action_url}})",
                'format' => 'markdown',
                'variables' => ['user.name', 'ticket.id', 'ticket.subject', 'reply_content', 'action_url'],
            ],
            [
                'key' => 'admin_new_user',
                'name' => 'Admin: New User Registration',
                'subject' => '[Admin] New User Registered: {{user.email}}',
                'body' => "A new user has just registered on {{app.name}}.\n\n- **Name:** {{user.name}}\n- **Email:** {{user.email}}\n- **IP:** {{user.ip}}\n\n[View User in Admin]({{admin_url}})",
                'format' => 'markdown',
                'variables' => ['user.name', 'user.email', 'user.ip', 'app.name', 'admin_url'],
            ],
            [
                'key' => 'admin_new_order',
                'name' => 'Admin: New Order Received',
                'subject' => '[Admin] New Order Alert! #{{order.id}}',
                'body' => "A new order has been placed.\n\n- **User:** {{user.email}}\n- **Amount:** {{order.amount}}\n- **Order ID:** {{order.id}}\n\n[View Order]({{admin_url}})",
                'format' => 'markdown',
                'variables' => ['user.email', 'order.id', 'order.amount', 'admin_url'],
            ],
            [
                'key' => 'admin_manual_payment',
                'name' => 'Admin: Manual Payment Submitted',
                'subject' => '[Admin] Manual Payment Proof Submitted',
                'body' => "A user has submitted proof for a manual payment.\n\n- **User:** {{user.email}}\n- **Reference:** {{payment.reference}}\n\n[Verify Payment]({{admin_url}})",
                'format' => 'markdown',
                'variables' => ['user.email', 'payment.reference', 'admin_url'],
            ],
            [
                'key' => 'admin_new_ticket',
                'name' => 'Admin: New Support Ticket',
                'subject' => '[Admin] New Support Ticket: {{ticket.subject}}',
                'body' => "A new support ticket has been opened.\n\n- **User:** {{user.email}}\n- **Subject:** {{ticket.subject}}\n- **Priority:** {{ticket.priority}}\n\n[View Ticket]({{admin_url}})",
                'format' => 'markdown',
                'variables' => ['user.email', 'ticket.subject', 'ticket.priority', 'admin_url'],
            ],
        ];

        foreach ($templates as $template) {
            \App\Models\EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
