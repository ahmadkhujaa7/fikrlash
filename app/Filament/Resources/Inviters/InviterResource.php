<?php

namespace App\Filament\Resources\Inviters;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\Inviters\Pages\ManageInviters;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Do‘st taklifi (referal) reytingi: kim nechta odamni olib keldi va ulardan nechtasi faol bo‘ldi
 * (post yoki izoh yozdi). Faol taklifchilarni rag‘batlantirish uchun.
 */
class InviterResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'inviters';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Taklif qilganlar';

    protected static ?string $modelLabel = 'taklif qiluvchi';

    protected static ?string $pluralModelLabel = 'Taklif qilganlar';

    protected static bool $isGloballySearchable = false;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getEloquentQuery(): Builder
    {
        // Faol taklif qilinganlar: post yoki izoh yozganlar (o‘z-o‘ziga bog‘langan jadval — aniq taxallus bilan).
        $active = DB::table('users as inv')->selectRaw('COUNT(*)')
            ->whereColumn('inv.referred_by', 'users.id')->whereNull('inv.deleted_at')
            ->where(fn ($q) => $q
                ->whereExists(fn ($e) => $e->from('posts')->whereColumn('posts.user_id', 'inv.id'))
                ->orWhereExists(fn ($e) => $e->from('comments')->whereColumn('comments.user_id', 'inv.id')));

        return parent::getEloquentQuery()
            ->whereHas('invitees')
            ->withCount('invitees')
            ->withMax('invitees', 'created_at')
            ->addSelect(['active_invitees_count' => $active]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (User $r) => UserResource::getUrl('view', ['record' => $r]))
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(28)
                    ->state(fn (User $r) => InitialsAvatarProvider::urlFor($r)),
                TextColumn::make('name')->label('Foydalanuvchi')->searchable(['name', 'username'])->weight('medium')
                    ->description(fn (User $r) => '@'.$r->username),
                TextColumn::make('invitees_count')->label('Taklif qilgani')->numeric()->sortable()->weight('bold')->color('success'),
                TextColumn::make('active_invitees_count')->label('Shundan faol')->numeric()->sortable()
                    ->description(fn (User $r) => $r->invitees_count ? round($r->active_invitees_count / $r->invitees_count * 100).'%' : null),
                TextColumn::make('invitees_max_created_at')->label('Oxirgi taklif')->since()->sortable(),
                TextColumn::make('created_at')->label('O‘zi qo‘shilgan')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('invitees_count', 'desc')
            ->filters([
                SelectFilter::make('period')->label('Taklif davri')
                    ->options(['7' => 'Oxirgi 7 kunda', '30' => 'Oxirgi 30 kunda', '90' => 'Oxirgi 90 kunda'])
                    ->query(fn (Builder $q, array $data) => $data['value']
                        ? $q->whereHas('invitees', fn ($i) => $i->where('created_at', '>=', now()->subDays((int) $data['value'])))
                        : $q),
            ])
            ->emptyStateHeading('Hali hech kim do‘stini taklif qilmagan')
            ->emptyStateDescription('Foydalanuvchilar menyudagi "Do‘stlarni taklif qilish" sahifasidan shaxsiy havolasini ulashadi.')
            ->emptyStateIcon(Heroicon::OutlinedGift);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageInviters::route('/')];
    }
}
