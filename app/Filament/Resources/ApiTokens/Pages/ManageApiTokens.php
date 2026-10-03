<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use Filament\Resources\Pages\ManageRecords;

class ManageApiTokens extends ManageRecords
{
    protected static string $resource = ApiTokenResource::class;
}
