<?php

namespace App\Filament\Pages;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\Notification as UserNotification;
use App\Models\User;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Tizim bildirishnomasi yuborish: barcha faol foydalanuvchilarga yoki bitta foydalanuvchiga. */
class BroadcastNotification extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Bildirishnoma yuborish';

    protected static ?string $title = 'Tizim bildirishnomasi';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['audience' => 'all']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('audience')->label('Kimga')->required()->live()
                ->options(['all' => 'Barcha faol foydalanuvchilar', 'user' => 'Bitta foydalanuvchi']),
            Select::make('user_id')->label('Foydalanuvchi')->searchable()
                ->visible(fn (Get $get) => $get('audience') === 'user')->required(fn (Get $get) => $get('audience') === 'user')
                ->getSearchResultsUsing(fn (string $search) => User::query()->where('username', 'like', "{$search}%")->limit(20)->pluck('username', 'id')->all())
                ->getOptionLabelUsing(fn ($value) => User::query()->find($value)?->username),
            Textarea::make('message')->label('Xabar')->required()->maxLength(500)->rows(4),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send')
                ->footer([Actions::make([Action::make('send')->label('Yuborish')->submit('send')])]),
        ]);
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $now = now();
        $count = 0;

        $query = User::query()->where('status', UserStatus::Active)
            ->when($data['audience'] === 'user', fn ($q) => $q->whereKey($data['user_id']));

        // Katta auditoriya uchun chunk + bulk insert.
        $query->select('id')->chunkById(1000, function ($users) use ($data, $now, &$count) {
            UserNotification::query()->insert($users->map(fn ($u) => [
                'user_id' => $u->id,
                'type' => NotificationType::System->value,
                'data' => json_encode(['message' => $data['message']], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
            $count += $users->count();
        });

        app(AuditLogger::class)->log('notification.broadcast', null, [], ['audience' => $data['audience'], 'recipients' => $count, 'message' => $data['message']]);
        Notification::make()->title("{$count} ta foydalanuvchiga yuborildi")->success()->send();
        $this->form->fill(['audience' => 'all']);
    }
}
