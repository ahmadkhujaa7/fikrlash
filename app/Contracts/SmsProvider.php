<?php

namespace App\Contracts;

use App\Exceptions\SmsDeliveryException;

interface SmsProvider
{
    /**
     * @param  string  $phone  E.164 formatdagi raqam (+998...)
     *
     * @throws SmsDeliveryException
     */
    public function send(string $phone, string $message): void;
}
