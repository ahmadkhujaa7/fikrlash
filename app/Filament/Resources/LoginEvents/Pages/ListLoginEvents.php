<?php

namespace App\Filament\Resources\LoginEvents\Pages;

use App\Filament\Resources\LoginEvents\LoginEventResource;
use App\Models\LoginEvent;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLoginEvents extends ListRecords
{
    protected static string $resource = LoginEventResource::class;

    public function getTabs(): array
    {
        $failed24 = LoginEvent::query()->whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDay())->count();

        return [
            'all' => Tab::make('Hammasi'),
            'logins' => Tab::make('Kirishlar')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('event', ['login', 'register'])),
            'failed' => Tab::make('Muvaffaqiyatsiz')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('event', ['failed', 'blocked']))
                ->badge($failed24 ?: null)->badgeColor('danger'),
            'admin' => Tab::make('Admin amallari')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('event', ['impersonate', 'impersonate_end', 'sessions_revoked'])),
        ];
    }
}
