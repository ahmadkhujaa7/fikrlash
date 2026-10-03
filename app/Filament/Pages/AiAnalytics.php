<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AiUsageStats;
use App\Filament\Widgets\LatestAiAnalyses;
use App\Jobs\AnalyzePostJob;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AiAnalytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI analitika';

    protected static ?string $title = 'AI analitika va xarajatlar';

    protected function getHeaderWidgets(): array
    {
        return [AiUsageStats::class, LatestAiAnalyses::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')->label('Tahlil qilinmaganlarni navbatga qo‘yish')->icon(Heroicon::OutlinedSparkles)
                ->requiresConfirmation()
                ->action(function () {
                    $ids = Post::query()->published()->whereNull('ai_analyzed_at')->limit(200)->pluck('id');
                    $ids->each(fn ($id) => AnalyzePostJob::dispatchSafely($id));
                    Notification::make()->title($ids->count().' ta post navbatga qo‘yildi')->success()->send();
                }),
        ];
    }
}
