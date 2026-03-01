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
        return ['mail'];
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
