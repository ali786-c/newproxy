<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\EmailTemplateService;
use Illuminate\Support\HtmlString;

abstract class BaseDynamicNotification extends Notification
{
    use Queueable;

    protected string $templateKey;
    protected array $templateData;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $templateKey, array $templateData = [])
    {
        $this->templateKey = $templateKey;
        $this->templateData = $templateData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['mail'];
        
        // Only include database channel if the notifiable supports it (e.g. real User model)
        if (is_object($notifiable) && method_exists($notifiable, 'notifications')) {
            $channels[] = 'database';
        }
        
        return $channels;
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toDatabase($notifiable): array
    {
        $service = app(EmailTemplateService::class);
        $render = $service->render($this->templateKey, $this->templateData);

        // Determine type based on template key prefix
        $type = 'alert';
        if (str_contains($this->templateKey, 'payment')) $type = 'payment';
        if (str_contains($this->templateKey, 'support') || str_contains($this->templateKey, 'ticket')) $type = 'support';
        if (str_contains($this->templateKey, 'security') || str_contains($this->templateKey, 'login')) $type = 'security';
        if (str_contains($this->templateKey, 'promo')) $type = 'promo';

        return [
            'type'    => $type,
            'title'   => $render['subject'],
            'message' => strip_tags($render['html']), // Simple text for the menu
            'data'    => $this->templateData,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $service = app(EmailTemplateService::class);
        
        // Merge common data like app name
        $data = array_merge([
            'app' => [
                'name' => config('app.name'),
                'url'  => config('app.url'),
            ],
        ], $this->templateData);

        $render = $service->render($this->templateKey, $data);

        return (new MailMessage)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($render['subject'])
            ->view('emails.dynamic', ['html' => $render['html']]);
    }
}
