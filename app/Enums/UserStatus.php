<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';
    case Deactivated = 'deactivated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Faol',
            self::Suspended => 'Vaqtincha cheklangan',
            self::Blocked => 'Bloklangan',
            self::Deactivated => 'O‘chirib qo‘yilgan',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
