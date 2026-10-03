<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case User = 'user';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'Foydalanuvchi',
            self::Admin => 'Administrator',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
