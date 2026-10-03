<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;

/** Testlar uchun: yuborilgan xabarlar xotirada saqlanadi. */
class ArraySmsProvider implements SmsProvider
{
    /** @var list<array{phone: string, message: string}> */
    public array $messages = [];

    public function send(string $phone, string $message): void
    {
        $this->messages[] = ['phone' => $phone, 'message' => $message];
    }

    public function lastMessageFor(string $phone): ?string
    {
        foreach (array_reverse($this->messages) as $sms) {
            if ($sms['phone'] === $phone) {
                return $sms['message'];
            }
        }

        return null;
    }

    /** Xabardan 6 xonali kodni ajratib oladi. */
    public function lastCodeFor(string $phone): ?string
    {
        $message = $this->lastMessageFor($phone);

        return $message && preg_match('/\b(\d{6})\b/', $message, $m) ? $m[1] : null;
    }
}
