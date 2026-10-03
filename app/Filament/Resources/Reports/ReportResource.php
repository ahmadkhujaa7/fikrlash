<?php

namespace App\Filament\Resources\Reports;

use App\Enums\PostStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\Moderation\ModerationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderatsiya';

    protected static ?string $modelLabel = 'shikoyat';

    protected static ?string $pluralModelLabel = 'Shikoyatlar';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'reviewer', 'reportable']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Report::query()->where('status', ReportStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reason')->label('Sabab')->badge()->color('danger'),
                TextColumn::make('reportable_type')->label('Turi')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => ['post' => 'Post', 'comment' => 'Izoh', 'user' => 'Foydalanuvchi'][$state] ?? $state),
                TextColumn::make('target')->label('Kontent')->wrap()->limit(80)
                    ->state(fn (Report $r) => self::targetLabel($r))
                    ->url(fn (Report $r) => self::targetUrl($r), true),
                TextColumn::make('description')->label('Izoh')->limit(60)->placeholder('—')->toggleable(),
                TextColumn::make('user.username')->label('Kim yubordi')->prefix('@'),
                TextColumn::make('status')->label('Holat')->badge()->color(fn (Report $r) => match ($r->status) {
                    ReportStatus::Pending => 'warning',
                    ReportStatus::Reviewing => 'info',
                    ReportStatus::Resolved => 'success',
                    ReportStatus::Rejected => 'gray',
                }),
                TextColumn::make('reviewer.username')->label('Ko‘rib chiqdi')->prefix('@')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(ReportStatus::class)->default(ReportStatus::Pending->value),
                SelectFilter::make('reason')->label('Sabab')->options(ReportReason::class),
                SelectFilter::make('reportable_type')->label('Turi')->options(['post' => 'Post', 'comment' => 'Izoh', 'user' => 'Foydalanuvchi']),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('resolve')->label('Hal qilish')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                        ->visible(fn (Report $r) => in_array($r->status, [ReportStatus::Pending, ReportStatus::Reviewing], true))
                        ->schema([
                            Select::make('action')->label('Kontentga nisbatan chora')->default('none')->required()
                                ->options(fn (Report $record) => self::actionOptions($record)),
                            Textarea::make('note')->label('Izoh (ichki)')->maxLength(500),
                        ])
                        ->action(function (Report $record, array $data) {
                            self::applyAction($record, $data['action']);
                            app(ModerationService::class)->resolveReport($record, ReportStatus::Resolved, auth()->user(), $data['note'] ?? null);
                            Notification::make()->title('Shikoyat hal qilindi')->success()->send();
                        }),
                    Action::make('reject')->label('Rad etish')->icon(Heroicon::OutlinedXCircle)->color('gray')
                        ->visible(fn (Report $r) => in_array($r->status, [ReportStatus::Pending, ReportStatus::Reviewing], true))
                        ->schema([Textarea::make('note')->label('Izoh (ichki)')->maxLength(500)])
                        ->action(function (Report $record, array $data) {
                            app(ModerationService::class)->resolveReport($record, ReportStatus::Rejected, auth()->user(), $data['note'] ?? null);
                            // Post faqat reportlar tufayli tekshiruvga tushgan bo‘lsa — qayta chop etiladi.
                            if ($record->reportable instanceof Post && $record->reportable->status === PostStatus::PendingModeration && ! $record->reportable->ai_flagged) {
                                app(ModerationService::class)->publishPost($record->reportable, auth()->user());
                            }
                            Notification::make()->title('Shikoyat rad etildi')->success()->send();
                        }),
                    Action::make('reviewing')->label('Ko‘rib chiqilmoqda')->icon(Heroicon::OutlinedEye)
                        ->visible(fn (Report $r) => $r->status === ReportStatus::Pending)
                        ->action(fn (Report $record) => app(ModerationService::class)->resolveReport($record, ReportStatus::Reviewing, auth()->user())),
                ]),
            ]);
    }

    private static function actionOptions(Report $report): array
    {
        return match (true) {
            $report->reportable instanceof Post => ['none' => 'Chora ko‘rilmaydi', 'hide' => 'Postni yashirish', 'delete' => 'Postni o‘chirish', 'suspend' => 'Muallifni 7 kunga cheklash'],
            $report->reportable instanceof Comment => ['none' => 'Chora ko‘rilmaydi', 'hide' => 'Izohni yashirish', 'suspend' => 'Muallifni 7 kunga cheklash'],
            $report->reportable instanceof User => ['none' => 'Chora ko‘rilmaydi', 'suspend' => '7 kunga cheklash', 'block' => 'Bloklash'],
            default => ['none' => 'Chora ko‘rilmaydi'],
        };
    }

    private static function applyAction(Report $report, string $action): void
    {
        $moderation = app(ModerationService::class);
        $admin = auth()->user();
        $target = $report->reportable;
        if (! $target || $action === 'none') {
            return;
        }

        $author = $target instanceof User ? $target : $target->user;
        $reason = 'Shikoyat: '.$report->reason->getLabel();

        match ($action) {
            'hide' => $target instanceof Post ? $moderation->hidePost($target, $admin, $reason) : $moderation->hideComment($target, $admin),
            'delete' => $moderation->deletePost($target, $admin, $reason),
            'suspend' => $author->isAdmin() ?: $moderation->suspendUser($author, now()->addDays(7), $admin, $reason),
            'block' => $author->isAdmin() ?: $moderation->blockUser($author, $admin, $reason),
            default => null,
        };
    }

    private static function targetLabel(Report $r): string
    {
        $t = $r->reportable;

        return match (true) {
            $t instanceof Post, $t instanceof Comment => ($t->trashed() ? '[o‘chirilgan] ' : '').mb_strimwidth($t->content, 0, 120, '…'),
            $t instanceof User => '@'.$t->username.' — '.$t->name,
            default => '[topilmadi]',
        };
    }

    private static function targetUrl(Report $r): ?string
    {
        $t = $r->reportable;

        return match (true) {
            $t instanceof Post && ! $t->trashed() => $t->url(),
            $t instanceof Comment && ! $t->trashed() => $t->url(),
            $t instanceof User && ! $t->trashed() => $t->profileUrl(),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return ['index' => ListReports::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
