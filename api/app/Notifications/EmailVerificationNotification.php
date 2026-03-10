<?php

namespace App\Notifications;

class EmailVerificationNotification extends BaseDynamicNotification
{
    /**
     * EmailVerificationNotification constructor.
     * 
     * @param string $code The 6-digit verification code.
     */
    public function __construct(string $code)
    {
        parent::__construct('email_verification', [
            'verification_code' => $code
        ]);
    }
}
