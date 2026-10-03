<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportReason: string implements HasLabel
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case Hate = 'hate';
    case Sexual = 'sexual';
    case Violence = 'violence';
    case Misinformation = 'misinformation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Spam => 'Spam yoki reklama',
            self::Abuse => 'Haqorat yoki tahqir',
            self::Hate => 'Nafrat yoki kamsitish',
            self::Sexual => 'Nomaqbul (18+) kontent',
            self::Violence => 'Zo‘ravonlik yoki tahdid',
            self::Misinformation => 'Yolg‘on maʼlumot',
            self::Other => 'Boshqa sabab',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
