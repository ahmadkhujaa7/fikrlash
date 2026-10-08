<?php

namespace App\Filament\Pages;

use App\Models\AuthorApplication;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Setting;
use App\Models\User;
use App\Services\Monetization\MonetizationService;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Monetizatsiya sozlamalari: kim so‘rov yubora oladi (obunachilar, ko‘rishlar, maqolalar),
 * narx (har N ko‘rish uchun X so‘m), eng kam yechish summasi, ko‘rish hisoblanishi uchun o‘qish vaqti.
 */
class MonetizationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Monetizatsiya';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Sozlamalar';

    protected static ?string $title = 'Monetizatsiya sozlamalari';

    protected static ?string $slug = 'monetization-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(MonetizationService::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::read($k, $v)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Holat')->schema([
                Text::make(fn () => $this->summary()),
                Toggle::make('monetization_enabled')->label('Monetizatsiya dasturi ochiq')
                    ->helperText('Yopilsa — yangi so‘rovlar qabul qilinmaydi va yangi daromad hisoblanmaydi. Mavjud balanslar va yechish so‘rovlari saqlanadi.'),
            ]),
            Section::make('So‘rov yuborish talablari')->description('Foydalanuvchi shu ko‘rsatkichlarga yetgach "Muallif bo‘lish" so‘rovini yubora oladi. Faqat maqolalar hisobga olinadi.')
                ->columns(3)->schema([
                    TextInput::make('monetization_min_followers')->label('Obunachilar')->numeric()->minValue(0)->required()->suffix('ta'),
                    TextInput::make('monetization_min_views')->label('Maqolalar ko‘rishlari (jami)')->numeric()->minValue(0)->required()->suffix('ta'),
                    TextInput::make('monetization_min_articles')->label('Chop etilgan maqolalar')->numeric()->minValue(0)->required()->suffix('ta'),
                ]),
            Section::make('Daromad')->description('Faqat muallif tasdiqlangandan KEYIN chop etilgan maqolalar uchun. Narx o‘zgarsa — oldin hisoblangan daromad o‘zgarmaydi.')
                ->columns(2)->schema([
                    TextInput::make('monetization_rate_amount')->label('Summa')->numeric()->minValue(0)->required()->suffix('so‘m'),
                    TextInput::make('monetization_rate_views')->label('… shuncha ko‘rish uchun')->numeric()->minValue(1)->required()->suffix('ko‘rish')
                        ->helperText('Masalan: 5 000 so‘m har 1 000 ko‘rish uchun.'),
                    TextInput::make('monetization_min_read_seconds')->label('Ko‘rish hisoblanishi uchun o‘qish vaqti')->numeric()->minValue(0)->maxValue(600)->required()->suffix('soniya')
                        ->helperText('Ro‘yxatdan o‘tgan o‘quvchi maqolani birinchi ochganda, kamida shuncha o‘qisa — 1 ko‘rish. Har o‘quvchi bir marta, muallifning o‘zi hisoblanmaydi.'),
                    TextInput::make('monetization_min_payout')->label('Eng kam yechish summasi')->numeric()->minValue(0)->required()->suffix('so‘m'),
                ]),
            Section::make('Shartlar')->schema([
                Textarea::make('monetization_terms')->label('So‘rov sahifasida ko‘rinadigan shartlar (ixtiyoriy)')->rows(4)->maxLength(2000),
            ]),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Saqlash')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $old = [];
        foreach (MonetizationService::DEFAULTS as $key => $default) {
            $old[$key] = Setting::read($key, $default);
            $value = $data[$key] ?? $default;
            $value = match ($key) {
                'monetization_enabled' => (bool) $value,
                'monetization_terms' => trim((string) $value) ?: null,
                'monetization_rate_amount', 'monetization_min_payout' => round(max(0, (float) $value), 2),
                'monetization_rate_views' => max(1, (int) $value),
                default => max(0, (int) $value),
            };
            Setting::write($key, $value);
        }

        app(AuditLogger::class)->log('monetization.settings', null, $old, $data);
        Notification::make()->title('Sozlamalar saqlandi')->success()->send();
    }

    private function summary(): string
    {
        $authors = User::query()->whereNotNull('monetized_at')->count();
        $pending = AuthorApplication::query()->where('status', AuthorApplication::PENDING)->count();
        $earned = (float) AuthorEarning::query()->sum('amount');
        $paid = (float) AuthorPayout::query()->where('status', AuthorPayout::PAID)->sum('amount');
        $waiting = (float) AuthorPayout::query()->where('status', AuthorPayout::PENDING)->sum('amount');

        return "Mualliflar: {$authors} · Ko‘rib chiqilmagan so‘rovlar: {$pending} · Jami hisoblangan: ".MonetizationService::money($earned)
            .' · To‘langan: '.MonetizationService::money($paid).' · To‘lov kutilmoqda: '.MonetizationService::money($waiting);
    }
}
