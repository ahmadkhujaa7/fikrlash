<?php

namespace App\Exceptions;

use RuntimeException;

/** OTP bilan bog‘liq foydalanuvchiga ko‘rsatiladigan xatolar. */
class OtpException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
