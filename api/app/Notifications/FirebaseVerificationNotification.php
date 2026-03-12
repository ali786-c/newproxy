<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Setting;

class FirebaseVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $verificationUrl;
    protected $userName;

    public function __construct($verificationUrl, $userName)
    {
        $this->verificationUrl = $verificationUrl;
        $this->userName = $userName;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $appName = Setting::getValue('app_name', 'UpgradedProxy');
        
        return (new MailMessage)
            ->subject('Verify Your Email - ' . $appName)
            ->greeting('Hello ' . $this->userName . '!')
            ->line('Thank you for signing up for ' . $appName . '.')
            ->line('Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email', $this->verificationUrl)
            ->line('This link will take you to Firebase to confirm your verification.')
            ->line('After verifying, please return to the app to access your dashboard.')
            ->line('If you did not create an account, no further action is required.');
    }
}
