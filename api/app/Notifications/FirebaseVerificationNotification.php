<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\BaseDynamicNotification;

class FirebaseVerificationNotification extends BaseDynamicNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     * 
     * @param string $verificationUrl The Firebase verification link
     * @param string $userName The name of the user
     */
    public function __construct($verificationUrl, $userName)
    {
        parent::__construct('email_verification', [
            'verification_url' => $verificationUrl,
            'user' => [
                'name' => $userName
            ]
        ]);
    }
}
