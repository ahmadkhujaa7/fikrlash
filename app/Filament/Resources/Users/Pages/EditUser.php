<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Social\AuditLogger;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private array $original = [];

    protected function beforeSave(): void
    {
        $this->original = $this->record->only(['name', 'username', 'email', 'role', 'bio']);
    }

    /** role fillable emas — admin forma orqali ataylab o‘zgartiradi. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        return $record;
    }

    protected function afterSave(): void
    {
        app(AuditLogger::class)->log('user.updated', $this->record, $this->original, $this->record->only(array_keys($this->original)));
    }
}
