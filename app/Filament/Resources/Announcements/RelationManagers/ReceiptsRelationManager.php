<?php

namespace App\Filament\Resources\Announcements\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use App\Models\AnnouncementReceipt;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Kim e'lonni ko‘rdi va kim bosib ochdi. */
class ReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'receipts';

    protected static ?string $title = 'Ko‘rganlar';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedEye;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Foydalanuvchi')->searchable()
                    ->description(fn (AnnouncementReceipt $r) => $r->user ? '@'.$r->user->username : null)
                    ->url(fn (AnnouncementReceipt $r) => $r->user && ! $r->user->trashed() ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('seen_at')->label('Ro‘yxatda ko‘rdi')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('opened_at')->label('Bosib ochdi')->dateTime('d.m.Y H:i')->sortable()->placeholder('Ochmagan')
                    ->badge()->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('seen_at', 'desc')
            ->filters([
                TernaryFilter::make('opened')->label('Bosib ochganmi')
                    ->trueLabel('Ochganlar')->falseLabel('Faqat ko‘rganlar')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('opened_at'),
                        false: fn (Builder $q) => $q->whereNull('opened_at'),
                    ),
            ]);
    }
}
