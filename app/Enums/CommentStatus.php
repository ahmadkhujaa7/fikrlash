<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommentStatus: string implements HasLabel
{
    case Published = 'published';
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::Published => 'Chop etilgan',
            self::Hidden => 'Yashirilgan',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
