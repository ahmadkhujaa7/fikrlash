<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OtpPurpose: string implements HasLabel
{
    case Register = 'register';
    case PasswordReset = 'password_reset';
    case PhoneChange = 'phone_change';

    public function getLabel(): string
    {
        return match ($this) {
            self::Register => 'Ro‘yxatdan o‘tish',
            self::PasswordReset => 'Parolni tiklash',
            self::PhoneChange => 'Telefonni almashtirish',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
