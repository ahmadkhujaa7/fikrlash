<?php

namespace App\Exceptions;

use RuntimeException;

class AiException extends RuntimeException
{
    /** true — qayta urinish foydasiz (masalan noto‘g‘ri API kalit). */
    public function __construct(string $message, public readonly bool $permanent = false)
    {
        parent::__construct($message);
    }
}
