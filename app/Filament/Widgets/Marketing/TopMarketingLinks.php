<?php

namespace App\Filament\Widgets\Marketing;

use App\Filament\Resources\MarketingLinks\MarketingLinkResource;
use App\Models\MarketingLink;
use App\Services\Monetization\MonetizationService;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Eng samarali havolalar — tanlangan davrdagi ro‘yxatdan o‘tishlar bo‘yicha. */
class TopMarketingLinks extends TableWidget
{
    use InteractsWithPageFilters;
    use MarketingPeriod;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Eng samarali havolalar')
            ->description('Tanlangan davrda — ro‘yxatdan o‘tganlar soni bo‘yicha.')
            // Davr sahifa filtridan har safar qayta o‘qiladi.
            ->query(function () {
                $since = $this->periodStart();

                return MarketingLink::query()
                    ->withCount([
                        'visits as period_clicks' => fn (Builder $q) => $q->where('created_at', '>=', $since),
                        'users as period_signups' => fn (Builder $q) => $q->where('created_at', '>=', $since),
                    ])
                    ->where(fn (Builder $q) => $q->where('last_click_at', '>=', $since)->orWhere('created_at', '>=', $since)->orWhere('signups_count', '>', 0));
            })
            ->defaultSort('period_signups', 'desc')
            ->recordUrl(fn (MarketingLink $r) => MarketingLinkResource::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('name')->label('Havola')->weight('medium')
                    ->description(fn (MarketingLink $r) => preg_replace('#^https?://#', '', $r->url())),
                TextColumn::make('channel')->label('Kanal')->badge()
                    ->formatStateUsing(fn (MarketingLink $r) => $r->channelLabel())
                    ->color(fn (MarketingLink $r) => MarketingLink::CHANNEL_COLORS[$r->channel] ?? 'gray'),
                TextColumn::make('period_clicks')->label('Bosishlar')->numeric()->sortable(),
                TextColumn::make('period_signups')->label('Ro‘yxatdan o‘tdi')->numeric()->sortable()->weight('bold')->color('success'),
                TextColumn::make('rate')->label('Bosishdan a’zoga')
                    ->state(fn (MarketingLink $r) => $r->period_clicks ? round($r->period_signups / $r->period_clicks * 100, 1).'%' : '—'),
                TextColumn::make('signups_count')->label('Jami a’zo')->numeric()->sortable()->color('gray')
                    ->description(fn (MarketingLink $r) => $r->costPerSignup() !== null ? '1 a’zo: '.MonetizationService::money(round($r->costPerSignup())) : null),
            ])
            ->emptyStateHeading('Bu davrda havolalar ishlatilmagan')
            ->emptyStateIcon(Heroicon::OutlinedLink)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
