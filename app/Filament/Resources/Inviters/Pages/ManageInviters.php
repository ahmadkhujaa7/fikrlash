<?php

namespace App\Filament\Resources\Inviters\Pages;

use App\Filament\Resources\Inviters\InviterResource;
use Filament\Resources\Pages\ManageRecords;

class ManageInviters extends ManageRecords
{
    protected static string $resource = InviterResource::class;

    public function getSubheading(): ?string
    {
        return 'Kim nechta do‘stini olib keldi. "Faol" — post yoki izoh yozganlar.';
    }
}
