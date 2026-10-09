<?php

namespace App\Filament\Widgets;

use App\Models\PostAiAnalysis;
use App\Services\Ai\AiConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** AI xarajati va sifat ko‘rsatkichlari (AI tahlil sahifasida). */
class AiUsageStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $since = now()->subDays(30);
        $done = PostAiAnalysis::query()->where('status', PostAiAnalysis::STATUS_COMPLETED)->where('created_at', '>=', $since);
        $total = (clone $done)->count();
        $failed = PostAiAnalysis::query()->where('status', PostAiAnalysis::STATUS_FAILED)->where('created_at', '>=', $since)->count();
        $cost = (clone $done)->get(['provider', 'input_tokens', 'output_tokens'])->sum(fn (PostAiAnalysis $a) => $a->estimatedCost());
        $tokens = (int) (clone $done)->sum('input_tokens') + (int) (clone $done)->sum('output_tokens');

        return [
            Stat::make('Tahlillar (30 kun)', Number::format($total))
                ->description('Bugun: '.PostAiAnalysis::query()->where('created_at', '>=', today())->count()),
            Stat::make('Xatolar', $failed)->color($failed > 0 ? 'danger' : 'success')
                ->description($total + $failed > 0 ? round($failed / ($total + $failed) * 100, 1).'% xato darajasi' : '—'),
            Stat::make('Tokenlar', Number::abbreviate($tokens))->description('Kesh orqali tejaldi: '.(clone $done)->where('provider', 'cache')->count().' ta'),
            Stat::make('Taxminiy xarajat', '$'.number_format($cost, 2))->description('AI sozlamalaridagi narxlar bo‘yicha'),
            Stat::make('O‘rtacha sifat', round((float) (clone $done)->avg('quality_score'), 1).'/100'),
            Stat::make('Xavfli deb topilgan', (clone $done)->where(fn ($q) => $q
                ->where('toxicity_score', '>=', AiConfig::thresholds()['toxicity_review'])
                ->orWhere('spam_score', '>=', AiConfig::thresholds()['spam_review']))->count())->color('warning'),
            Stat::make('O‘rtacha javob vaqti', round((float) (clone $done)->where('provider', '!=', 'cache')->avg('latency_ms')).' ms'),
            Stat::make('Provayder', AiConfig::PROVIDERS[AiConfig::provider()])->description(AiConfig::enabled() ? 'Yoqilgan' : 'O‘chirilgan'),
        ];
    }
}
