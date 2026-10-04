<?php

namespace App\Filament\Resources\LoginEvents;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\LoginEvents\Pages\ListLoginEvents;
use App\Filament\Resources\Users\UserResource;
use App\Models\LoginEvent;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Barcha kirishlar, chiqishlar va muvaffaqiyatsiz urinishlar — faqat o‘qish uchun. */
class LoginEventResource extends Resource
{
    protected static ?string $model = LoginEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Kuzatuv';

    protected static ?int $navigationSort = 10;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'kirish';

    protected static ?string $pluralModelLabel = 'Kirishlar tarixi';

    protected static ?string $slug = 'login-events';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'actor']))
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(28)
                    ->state(fn (LoginEvent $r) => $r->user ? InitialsAvatarProvider::urlFor($r->user) : null),
                TextColumn::make('user.name')->label('Foydalanuvchi')
                    ->description(fn (LoginEvent $r) => $r->user ? '@'.$r->user->username : ($r->identifier ? 'kiritilgan: “'.$r->identifier.'”' : null))
                    ->placeholder('Noma’lum')
                    ->url(fn (LoginEvent $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('event')->label('Hodisa')->badge()
                    ->formatStateUsing(fn (LoginEvent $r) => $r->label())
                    ->color(fn (LoginEvent $r) => LoginEvent::COLORS[$r->event] ?? 'gray'),
                TextColumn::make('channel')->label('Kanal')->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => ['web' => 'Sayt', 'api' => 'Ilova/API', 'admin' => 'Admin'][$state] ?? $state),
                TextColumn::make('device')->label('Qurilma')->placeholder('—')->toggleable(),
                TextColumn::make('ip')->label('IP')->fontFamily('mono')->copyable()->searchable(),
                TextColumn::make('identifier')->label('Kiritilgan login')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actor.username')->label('Admin')->prefix('@')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i:s')->sortable()
                    ->description(fn (LoginEvent $r) => $r->created_at->diffForHumans()),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('IP yoki login bo‘yicha')
            ->filters([
                SelectFilter::make('event')->label('Hodisa')->options(LoginEvent::LABELS)->multiple(),
                SelectFilter::make('channel')->label('Kanal')->options(['web' => 'Sayt', 'api' => 'Ilova/API', 'admin' => 'Admin']),
                Filter::make('period')->label('Davr')
                    ->schema([DatePicker::make('from')->label('Dan'), DatePicker::make('until')->label('Gacha')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginEvents::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
