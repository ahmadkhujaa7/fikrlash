<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Tahrirlash'),
            ...UserResource::verificationActions(),
            ActionGroup::make([...UserResource::accountActions(), ...UserResource::moderationActions()])
                ->label('Boshqa amallar')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
        ];
    }

    /** Bog‘liq ma'lumotlar (postlar, kirishlar, sessiyalar...) — tablar ko‘rinishida. */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }
}
