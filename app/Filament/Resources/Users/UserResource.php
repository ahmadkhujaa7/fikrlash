<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use App\Services\Moderation\ModerationService;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Foydalanuvchilar';

    protected static ?string $modelLabel = 'foydalanuvchi';

    protected static ?string $pluralModelLabel = 'Foydalanuvchilar';

    protected static ?string $recordTitleAttribute = 'username';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Ism')->required()->maxLength(100),
                TextInput::make('username')->required()->maxLength(30)
                    ->rule('regex:/^(?=.*[a-z])[a-z0-9_]+$/')
                    ->unique(ignoreRecord: true),
                TextInput::make('email')->email()->nullable()->unique(ignoreRecord: true),
                Select::make('role')->label('Rol')->options(UserRole::class)->required()
                    ->helperText('Admin roli admin panelga to‘liq kirish huquqini beradi.'),
                Textarea::make('bio')->maxLength(300)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('name')->label('Ism'),
                TextEntry::make('username')->prefix('@')->url(fn (User $r) => $r->trashed() ? null : $r->profileUrl(), true),
                TextEntry::make('phone')->label('Telefon')->formatStateUsing(fn ($state) => PhoneNumber::format($state)),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('role')->label('Rol')->badge(),
                TextEntry::make('status')->label('Holat')->badge()->color(fn (User $r) => self::statusColor($r->status)),
                TextEntry::make('suspended_until')->label('Cheklov muddati')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('followers_count')->label('Obunachilar'),
                TextEntry::make('following_count')->label('Obunalar'),
                TextEntry::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y H:i'),
                TextEntry::make('last_login_at')->label('Oxirgi kirish')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('deleted_at')->label('O‘chirilgan')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('bio')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ism')->searchable()->sortable()
                    ->description(fn (User $r) => '@'.$r->username),
                TextColumn::make('username')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->label('Telefon')->searchable()
                    ->formatStateUsing(fn ($state) => PhoneNumber::format($state))->toggleable(),
                TextColumn::make('status')->label('Holat')->badge()->color(fn (User $r) => self::statusColor($r->status)),
                TextColumn::make('role')->label('Rol')->badge()->color(fn (User $r) => $r->isAdmin() ? 'warning' : 'gray'),
                TextColumn::make('followers_count')->label('Obunachilar')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('last_active_at')->label('Faollik')->since()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(UserStatus::class),
                SelectFilter::make('role')->label('Rol')->options(UserRole::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...self::moderationActions(),
                ]),
            ]);
    }

    /** @return list<Action> */
    public static function moderationActions(): array
    {
        return [
            Action::make('suspend')
                ->label('Vaqtincha cheklash')
                ->icon(Heroicon::OutlinedPause)->color('warning')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->isAdmin() && $r->status === UserStatus::Active)
                ->schema([
                    DateTimePicker::make('until')->label('Qachongacha')->required()->minDate(now())->default(now()->addDays(3)),
                    Textarea::make('reason')->label('Sabab (foydalanuvchiga ko‘rinadi)')->maxLength(300),
                ])
                ->action(function (User $record, array $data) {
                    app(ModerationService::class)->suspendUser($record, Carbon::parse($data['until']), auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Foydalanuvchi cheklandi')->success()->send();
                }),
            Action::make('block')
                ->label('Bloklash')
                ->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->isAdmin() && $r->status !== UserStatus::Blocked)
                ->requiresConfirmation()
                ->modalDescription('Foydalanuvchi tizimdan chiqariladi, barcha tokenlari bekor qilinadi va kontenti yashiriladi.')
                ->schema([Textarea::make('reason')->label('Sabab (ichki)')->maxLength(300)])
                ->action(function (User $record, array $data) {
                    app(ModerationService::class)->blockUser($record, auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Foydalanuvchi bloklandi')->success()->send();
                }),
            Action::make('activate')
                ->label('Faollashtirish')
                ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (User $r) => ! $r->trashed() && in_array($r->status, [UserStatus::Blocked, UserStatus::Suspended], true))
                ->requiresConfirmation()
                ->action(function (User $record) {
                    app(ModerationService::class)->activateUser($record, auth()->user());
                    Notification::make()->title('Foydalanuvchi faollashtirildi')->success()->send();
                }),
        ];
    }

    public static function statusColor(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Suspended => 'warning',
            UserStatus::Blocked => 'danger',
            UserStatus::Deactivated => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // foydalanuvchilar faqat SMS tasdiqlash orqali ro‘yxatdan o‘tadi
    }
}
