<?php

namespace App\Filament\Widgets;

use App\Models\PostAiAnalysis;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestAiAnalyses extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('So‘nggi AI tahlillar')
            ->query(PostAiAnalysis::query()->with('post')->latest('id'))
            ->columns([
                TextColumn::make('post.content')->label('Post')->limit(60)->wrap(),
                TextColumn::make('status')->label('Holat')->badge()->color(fn ($state) => $state === 'completed' ? 'success' : 'danger'),
                TextColumn::make('category')->label('Kategoriya')->placeholder('—'),
                TextColumn::make('sentiment')->label('Kayfiyat')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('quality_score')->label('Sifat')->placeholder('—'),
                TextColumn::make('toxicity_score')->label('Toksik')->placeholder('—')->color(fn ($state) => $state >= config('ai.thresholds.toxicity_review') ? 'danger' : null),
                TextColumn::make('spam_score')->label('Spam')->placeholder('—')->color(fn ($state) => $state >= config('ai.thresholds.spam_review') ? 'danger' : null),
                TextColumn::make('provider')->label('Provayder')->description(fn (PostAiAnalysis $r) => $r->model),
                TextColumn::make('error')->label('Xato')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Vaqt')->since(),
            ])
            ->defaultPaginationPageOption(10);
    }
}
