<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Kim menga shaxsiy xabar yoza oladi. */
enum MessagePrivacy: string implements HasLabel
{
    case Everyone = 'everyone';
    case Following = 'following';
    case Nobody = 'nobody';

    public function getLabel(): string
    {
        return match ($this) {
            self::Everyone => 'Hamma',
            self::Following => 'Faqat men obuna bo‘lganlar',
            self::Nobody => 'Hech kim',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
