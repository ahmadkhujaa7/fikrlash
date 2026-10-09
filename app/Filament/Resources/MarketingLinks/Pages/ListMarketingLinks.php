<?php

namespace App\Filament\Resources\MarketingLinks\Pages;

use App\Filament\Pages\MarketingDashboard;
use App\Filament\Resources\MarketingLinks\MarketingLinkResource;
use App\Models\MarketingLink;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListMarketingLinks extends ListRecords
{
    protected static string $resource = MarketingLinkResource::class;

    public function getSubheading(): ?string
    {
        return 'Har bir kanal, bloger yoki reklama uchun alohida havola — kim qancha odam olib kelganini ko‘rasiz.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yangi havola')->icon(Heroicon::OutlinedPlus),
            Action::make('export')->label('CSV (Excel)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->exportCsv()),
            Action::make('dashboard')->label('Marketing paneli')->icon(Heroicon::OutlinedPresentationChartLine)->color('gray')
                ->url(MarketingDashboard::getUrl()),
        ];
    }

    /** Barcha havolalar (arxivdagilar ham) statistikasi — Excel'da ochiladigan CSV. */
    public function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel o‘zbekcha harflarni to‘g‘ri ko‘rsatishi uchun (UTF-8 BOM)
            fputcsv($out, ['Nomi', 'Havola', 'Kanal', 'Hamkor', 'Aloqa', 'Bosishlar', 'Noyob', 'Ro‘yxatni boshladi', 'Ro‘yxatdan o‘tdi',
                'Konversiya %', 'Xarajat (so‘m)', '1 a’zo narxi (so‘m)', 'Holat', 'Yaratilgan', 'Oxirgi bosish'], ';');

            MarketingLink::withTrashed()->orderBy('id')->each(function (MarketingLink $l) use ($out) {
                fputcsv($out, [
                    $l->name, $l->url(), $l->channelLabel(), $l->partner, $l->contact,
                    $l->clicks_count, $l->visitors_count, $l->starts_count, $l->signups_count,
                    $l->conversion(), $l->cost, $l->costPerSignup(),
                    MarketingLinkResource::status($l)[0], $l->created_at?->format('d.m.Y H:i'), $l->last_click_at?->format('d.m.Y H:i'),
                ], ';');
            });
            fclose($out);
        }, 'fikrlash-reklama-havolalari-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
