<?php

namespace App\Filament\Resources\AuthorPayouts;

use App\Filament\Resources\AuthorPayouts\Pages\ManageAuthorPayouts;
use App\Models\AuthorPayout;
use App\Services\Monetization\MonetizationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Pul yechish so‘rovlari: admin pulni kartaga o‘tkazib "To‘landi" deb belgilaydi yoki rad etadi. */
class AuthorPayoutResource extends Resource
{
    protected static ?string $model = AuthorPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Monetizatsiya';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pul yechish';

    protected static ?string $modelLabel = 'to‘lov';

    protected static ?string $pluralModelLabel = 'Pul yechish so‘rovlari';

    public static function getNavigationBadge(): ?string
    {
        $n = AuthorPayout::query()->where('status', AuthorPayout::PENDING)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'processor']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Muallif')->searchable()->weight('medium')
                    ->description(fn (AuthorPayout $r) => $r->user ? '@'.$r->user->username : null),
                TextColumn::make('amount')->label('Summa')->sortable()->weight('bold')
                    ->formatStateUsing(fn ($state) => MonetizationService::money((float) $state)),
                TextColumn::make('account')->label('Karta')->formatStateUsing(fn ($state, AuthorPayout $r) => $r->maskedAccount())
                    ->description(fn (AuthorPayout $r) => $r->holder),
                TextColumn::make('status')->label('Holat')->badge()
                    ->formatStateUsing(fn (string $state) => AuthorPayout::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => AuthorPayout::COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')->label('So‘ralgan')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('processed_at')->label('Ko‘rib chiqilgan')->dateTime('d.m.Y H:i')->placeholder('—')
                    ->description(fn (AuthorPayout $r) => $r->reference ?: $r->admin_note)->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(AuthorPayout::LABELS)->default(AuthorPayout::PENDING),
            ])
            ->emptyStateHeading('Yechish so‘rovlari yo‘q')
            ->emptyStateIcon(Heroicon::OutlinedCreditCard)
            ->recordActions([
                Action::make('card')->label('Karta')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->modalHeading('To‘lov ma’lumotlari')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Yopish')
                    ->schema(fn (AuthorPayout $record) => [
                        TextInput::make('account')->label('Karta raqami')->default(chunk_split((string) $record->account, 4, ' '))->readOnly()->copyable(),
                        TextInput::make('holder')->label('Karta egasi')->default($record->holder)->readOnly(),
                        TextInput::make('amount')->label('Summa')->default(MonetizationService::money((float) $record->amount))->readOnly(),
                    ]),
                Action::make('paid')->label('To‘landi')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (AuthorPayout $r) => $r->status === AuthorPayout::PENDING)
                    ->modalHeading('Pul o‘tkazildimi?')
                    ->modalDescription('Avval pulni muallif kartasiga o‘tkazing, keyin shu yerda belgilang. Muallifga bildirishnoma boradi.')
                    ->schema([
                        TextInput::make('reference')->label('Tranzaksiya / chek raqami (ixtiyoriy)')->maxLength(120),
                        Textarea::make('note')->label('Izoh (ixtiyoriy)')->maxLength(500),
                    ])
                    ->action(function (AuthorPayout $record, array $data) {
                        app(MonetizationService::class)->markPaid($record, auth()->user(), $data['reference'] ?? null, $data['note'] ?? null);
                        Notification::make()->title('To‘landi deb belgilandi')->success()->send();
                    }),
                Action::make('reject')->label('Rad etish')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (AuthorPayout $r) => $r->status === AuthorPayout::PENDING)
                    ->schema([Textarea::make('reason')->label('Sabab (summa balansga qaytadi)')->required()->maxLength(500)])
                    ->action(function (AuthorPayout $record, array $data) {
                        app(MonetizationService::class)->rejectPayout($record, auth()->user(), $data['reason']);
                        Notification::make()->title('So‘rov rad etildi')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuthorPayouts::route('/')];
    }
}
