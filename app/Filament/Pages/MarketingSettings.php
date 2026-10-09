<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Marketing\MarketingService;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Marketing sozlamalari: atributsiya muddati, do‘st taklifi, reklama tizimlari (Meta Pixel,
 * Google Analytics 4, Yandex Metrika) — ID kiritilsa skript saytga avtomatik qo‘shiladi.
 */
class MarketingSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Sozlamalar';

    protected static ?string $title = 'Marketing sozlamalari';

    protected static ?string $slug = 'marketing-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(MarketingService::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::read($k, $v)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Qanday ishlaydi')->collapsible()->schema([
                Text::make(new HtmlString(
                    '<b>Reklama havolasi</b>: "Reklama havolalari" bo‘limida masalan <b>Tg1</b> nomli havola yarating → <code>'.e(preg_replace('#^https?://#', '', url('/r/tg1'))).'</code>. '
                    .'Uni kanal adminiga bering. Shu havola orqali kirganlar va ro‘yxatdan o‘tganlar alohida hisoblanadi.<br>'
                    .'<b>Istalgan sahifa</b>: manzil oxiriga <code>?ref=tg1</code> qo‘shsangiz ham ishlaydi (masalan maqola havolasi).<br>'
                    .'<b>UTM</b>: <code>?utm_source=instagram&amp;utm_campaign=kuz</code> bilan kelganlar ham "UTM" manbasi sifatida yoziladi.<br>'
                    .'<b>Do‘st taklifi</b>: har bir foydalanuvchida shaxsiy havola bor (<code>'.e(preg_replace('#^https?://#', '', url('/i/username'))).'</code>) — menyuda "Do‘stlarni taklif qilish".'
                )),
            ]),
            Section::make('Hisoblash')->columns(2)->schema([
                TextInput::make('marketing_attribution_days')->label('Atributsiya muddati')->numeric()->integer()->minValue(1)->maxValue(365)->required()->suffix('kun')
                    ->helperText('Havolani bosgan odam shu muddat ichida ro‘yxatdan o‘tsa — havolaga yoziladi. Odatda 30 kun.'),
                Toggle::make('marketing_invites_enabled')->label('Do‘st taklifi (referal) yoqilgan')
                    ->helperText('Foydalanuvchilar shaxsiy havola bilan do‘stlarini taklif qila oladi; do‘sti qo‘shilganda xabar oladi.'),
            ]),
            Section::make('Reklama va analitika tizimlari')
                ->description('ID kiritilsa — kod saytning barcha sahifalariga qo‘shiladi va ro‘yxatdan o‘tish "konversiya" sifatida yuboriladi (target reklamani optimallashtirish uchun). Bo‘sh qoldirilsa — o‘chiq.')
                ->columns(3)->schema([
                    TextInput::make('marketing_meta_pixel')->label('Meta (Facebook/Instagram) Pixel ID')->maxLength(20)
                        ->regex('/^\d{6,20}$/')->validationMessages(['regex' => 'Pixel ID faqat raqamlardan iborat (6–20 ta).'])
                        ->placeholder('123456789012345')
                        ->helperText('Events Manager → Data sources. Hodisalar: PageView, CompleteRegistration.'),
                    TextInput::make('marketing_ga4')->label('Google Analytics 4 ID')->maxLength(24)
                        ->regex('/^G-[A-Z0-9]{4,20}$/i')->validationMessages(['regex' => 'Format: G-XXXXXXXXXX'])
                        ->placeholder('G-XXXXXXXXXX')
                        ->helperText('Admin → Data streams → Measurement ID. Hodisa: sign_up.'),
                    TextInput::make('marketing_yandex_metrika')->label('Yandex Metrika hisoblagich ID')->maxLength(12)
                        ->regex('/^\d{4,12}$/')->validationMessages(['regex' => 'Hisoblagich raqami (4–12 ta raqam).'])
                        ->placeholder('98765432')
                        ->helperText('Maqsad (goal) identifikatori: signup.'),
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
        foreach (MarketingService::DEFAULTS as $key => $default) {
            $old[$key] = Setting::read($key, $default);
            $value = $data[$key] ?? $default;
            $value = match ($key) {
                'marketing_attribution_days' => max(1, min(365, (int) $value)),
                'marketing_invites_enabled' => (bool) $value,
                'marketing_ga4' => ($v = strtoupper(trim((string) $value))) !== '' ? $v : null,
                default => ($v = trim((string) $value)) !== '' ? $v : null,
            };
            Setting::write($key, $value);
        }

        app(AuditLogger::class)->log('marketing.settings', null, $old, $data);
        Notification::make()->title('Sozlamalar saqlandi')->success()->send();
    }
}
