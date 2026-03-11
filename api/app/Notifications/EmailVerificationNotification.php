<?php

namespace App\Notifications;

class EmailVerificationNotification extends BaseDynamicNotification
{
    /**
     * EmailVerificationNotification constructor.
     * 
     * @param string $url The signed verification URL.
     */
    public function __construct(string $url)
    {
        parent::__construct('email_verification', [
            'verification_url' => $url
        ]);
    }
}
