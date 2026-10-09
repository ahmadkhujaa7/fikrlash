<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\UserSession;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yangi foydalanuvchi')->icon(Heroicon::OutlinedUserPlus),
        ];
    }

    /** Tezkor bo‘limlar — har birida son ko‘rinadi. */
    public function getTabs(): array
    {
        $count = fn (callable $scope) => (string) $scope(UserResource::getEloquentQuery())->count();

        return [
            'all' => Tab::make('Hammasi'),
            'online' => Tab::make('Onlayn')->icon(Heroicon::Signal)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('id', UserSession::query()->online()->whereNotNull('user_id')->select('user_id')))
                ->badge((string) UserResource::onlineCount())->badgeColor('success'),
            'today' => Tab::make('Bugun qo‘shilgan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', today()))
                ->badge($count(fn ($q) => $q->where('created_at', '>=', today()))),
            'verified' => Tab::make('Tasdiqlangan')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('verified_at')),
            'authors' => Tab::make('Mualliflar')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('monetized_at')),
            'restricted' => Tab::make('Cheklangan / bloklangan')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [UserStatus::Suspended, UserStatus::Blocked]))
                ->badge($count(fn ($q) => $q->whereIn('status', [UserStatus::Suspended, UserStatus::Blocked])))->badgeColor('danger'),
            'admins' => Tab::make('Adminlar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::Admin)),
        ];
    }
}
