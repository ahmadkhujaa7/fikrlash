<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Admin va tizim amallari jurnali. Faqat o‘qish uchun: tahrirlab yoki o‘chirib bo‘lmaydi. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Kuzatuv';

    protected static ?int $navigationSort = 30;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'audit yozuvi';

    protected static ?string $pluralModelLabel = 'Admin amallari (audit)';

    public const ACTIONS = [
        'user.created_by_admin' => 'Foydalanuvchi yaratildi',
        'user.updated_by_admin' => 'Ma’lumotlari o‘zgartirildi',
        'user.password_set_by_admin' => 'Parol o‘zgartirildi',
        'user.verified' => 'Tasdiqlandi',
        'user.unverified' => 'Tasdiq olindi',
        'user.suspended' => 'Vaqtincha cheklandi',
        'user.blocked' => 'Bloklandi',
        'user.activated' => 'Faollashtirildi',
        'user.deactivated_by_admin' => 'O‘chirib qo‘yildi',
        'user.impersonated' => 'Admin uning nomidan kirdi',
        'user.impersonation_ended' => 'Admin qaytdi',
        'user.sessions_revoked' => 'Barcha qurilmalardan chiqarildi',
        'user.messaged' => 'Xabar yuborildi',
        'users.exported' => 'Foydalanuvchilar eksport qilindi',
        'session.terminated' => 'Sessiya tugatildi',
        'post.hidden' => 'Post yashirildi',
        'post.published' => 'Post chop etildi',
        'post.deleted' => 'Post o‘chirildi',
        'post.restored' => 'Post tiklandi',
        'comment.hidden' => 'Izoh yashirildi',
        'comment.deleted' => 'Izoh o‘chirildi',
        'comment.restored' => 'Izoh tiklandi',
        'report.resolved' => 'Shikoyat hal qilindi',
        'report.dismissed' => 'Shikoyat rad etildi',
        'settings.updated' => 'Sozlamalar o‘zgartirildi',
        'notification.broadcast' => 'Ommaviy xabar',
        'token.revoked' => 'Token bekor qilindi',
        'marketing.link_created' => 'Reklama havolasi yaratildi',
        'marketing.settings' => 'Marketing sozlamalari o‘zgartirildi',
    ];

    public static function actionLabel(?string $action): string
    {
        return self::ACTIONS[$action] ?? (string) $action;
    }

    /** "ism: Ali → Vali; rol: user → admin" ko‘rinishidagi qisqa tavsif. */
    public static function describeChanges(AuditLog $log): ?string
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $parts = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $from = self::stringify($old[$key] ?? null);
            $to = self::stringify($new[$key] ?? null);
            $parts[] = $from === null ? "{$key}: {$to}" : "{$key}: {$from} → {$to}";
        }

        return $parts ? mb_strimwidth(implode('; ', $parts), 0, 220, '…') : null;
    }

    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'ha' : 'yo‘q',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('action')->label('Amal')->badge()->formatStateUsing(fn ($state) => self::actionLabel($state)),
            TextEntry::make('user.username')->label('Kim')->prefix('@')->placeholder('tizim'),
            TextEntry::make('target_type')->label('Obyekt')->formatStateUsing(fn ($state, AuditLog $r) => "{$state} #{$r->target_id}"),
            TextEntry::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i:s'),
            TextEntry::make('ip_address')->label('IP')->placeholder('—'),
            TextEntry::make('user_agent')->label('User agent')->placeholder('—'),
            KeyValueEntry::make('old_values')->label('Oldingi qiymatlar')->columnSpanFull(),
            KeyValueEntry::make('new_values')->label('Yangi qiymatlar')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (AuditLog $r) => $r->created_at->diffForHumans()),
                TextColumn::make('action')->label('Amal')->badge()->searchable()
                    ->formatStateUsing(fn ($state) => self::actionLabel($state))
                    ->color(fn ($state) => match (true) {
                        str_contains((string) $state, 'blocked'), str_contains((string) $state, 'deleted') => 'danger',
                        str_contains((string) $state, 'impersonat'), str_contains((string) $state, 'suspended') => 'warning',
                        str_contains((string) $state, 'verified'), str_contains((string) $state, 'created') => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('user.username')->label('Kim')->prefix('@')->placeholder('tizim')->searchable(),
                TextColumn::make('target')->label('Obyekt')
                    ->state(fn (AuditLog $r) => $r->target_type ? "{$r->target_type} #{$r->target_id}" : '—')
                    ->url(fn (AuditLog $r) => $r->target_type === 'user' && User::withTrashed()->whereKey($r->target_id)->exists()
                        ? UserResource::getUrl('view', ['record' => $r->target_id]) : null),
                TextColumn::make('changes')->label('O‘zgarish')->wrap()
                    ->state(fn (AuditLog $r) => self::describeChanges($r))->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Amal')->multiple()
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                        ->mapWithKeys(fn ($a) => [$a => self::actionLabel($a)])->all()),
                SelectFilter::make('user_id')->label('Admin')
                    ->options(fn () => User::query()->where('role', 'admin')->pluck('username', 'id')->map(fn ($u) => '@'.$u)->all()),
                SelectFilter::make('target_type')->label('Obyekt turi')
                    ->options(['post' => 'Post', 'comment' => 'Izoh', 'user' => 'Foydalanuvchi', 'report' => 'Shikoyat', 'marketing_link' => 'Reklama havolasi']),
                Filter::make('period')->label('Davr')
                    ->schema([DatePicker::make('from')->label('Dan'), DatePicker::make('until')->label('Gacha')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
