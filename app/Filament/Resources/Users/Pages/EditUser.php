<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Account\AdminUserService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** Admin istalgan ma'lumotni o‘zgartiradi; barcha o‘zgarishlar AdminUserService orqali audit qilinadi. */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(AdminUserService::class)->update($record, $data, auth()->user());
    }

    protected function getRedirectUrl(): ?string
    {
        return UserResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'O‘zgarishlar saqlandi';
    }
}
