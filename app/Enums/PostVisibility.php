<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PostVisibility: string implements HasLabel
{
    case Public = 'public';
    case Followers = 'followers';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Hamma uchun',
            self::Followers => 'Faqat obunachilar',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
