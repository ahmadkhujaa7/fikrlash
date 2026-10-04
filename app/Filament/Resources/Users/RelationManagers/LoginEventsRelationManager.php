<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\LoginEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Kirishlar tarixi: qachon, qayerdan, qaysi qurilmadan; muvaffaqiyatsiz urinishlar ham. */
class LoginEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginEvents';

    protected static ?string $title = 'Kirishlar tarixi';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedFingerPrint;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $failed = $ownerRecord->loginEvents()->whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDay())->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        return 'So‘nggi 24 soatdagi muvaffaqiyatsiz urinishlar';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')->label('Hodisa')->badge()
                    ->formatStateUsing(fn (LoginEvent $r) => $r->label())
                    ->color(fn (LoginEvent $r) => LoginEvent::COLORS[$r->event] ?? 'gray'),
                TextColumn::make('channel')->label('Kanal')->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => ['web' => 'Sayt', 'api' => 'Ilova/API', 'admin' => 'Admin'][$state] ?? $state),
                TextColumn::make('device')->label('Qurilma')->placeholder('—'),
                TextColumn::make('ip')->label('IP')->copyable()->searchable()->fontFamily('mono'),
                TextColumn::make('actor.username')->label('Admin')->prefix('@')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i:s')->sortable()
                    ->description(fn (LoginEvent $r) => $r->created_at->diffForHumans()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('event')->label('Hodisa')->options(LoginEvent::LABELS)->multiple()]);
    }
}
