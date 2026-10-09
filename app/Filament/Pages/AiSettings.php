<?php

namespace App\Filament\Pages;

use App\Contracts\AiProvider;
use App\Models\Category;
use App\Models\PostAiAnalysis;
use App\Models\Setting;
use App\Services\Ai\AiConfig;
use App\Services\Ai\Providers\ClaudeProvider;
use App\Services\Ai\Providers\FakeProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Throwable;
use UnitEnum;

/**
 * AI ulanishi admin paneldan: provayder (Claude / OpenAI / sinov / o‘chiq), API kalit, model,
 * moderatsiya chegaralari va xarajat limitlari. Kalitlar bazada shifrlangan; .env qiymatlari
 * zaxira sifatida qoladi. "Ulanishni tekshirish" — saqlashdan oldin kalitni sinab ko‘rish.
 */
class AiSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'AI sozlamalari';

    protected static ?string $title = 'AI ulanishi va sozlamalari';

    protected static ?string $slug = 'ai-settings';

    private const SAMPLE = 'Salom! Bu Fikrlash.uz AI ulanishini tekshirish uchun qisqa sinov matni. Kitob o‘qish foydali odat.';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $data = [
            'provider' => AiConfig::provider(),
            'ai_enabled' => (bool) Setting::read('ai_enabled'),
            'ai_auto_moderation' => (bool) Setting::read('ai_auto_moderation'),
            ...AiConfig::thresholds(),
            'requests_per_minute' => AiConfig::requestsPerMinute(),
            'daily_limit' => AiConfig::dailyLimit(),
        ];

        foreach (AiConfig::REMOTE as $name) {
            $config = AiConfig::providerConfig($name);
            $data["{$name}_api_key"] = null;
            $data["{$name}_forget_key"] = false;
            foreach (AiConfig::FIELDS as $field) {
                $data["{$name}_{$field}"] = $config[$field] ?? null;
            }
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Holat')->schema([
                Text::make(fn () => $this->statusLine()),
                Text::make(fn () => $this->queueLine()),
                Callout::make('Navbat ishchisi ishlamayotganga o‘xshaydi')
                    ->description('AI vazifalari navbatda kutib qolmoqda, shuning uchun postlar tekshirilmayapti. Serverda doimiy ishlab turishi kerak: php artisan queue:work --queue=ai,default  (yoki sinov uchun .env da QUEUE_CONNECTION=sync).')
                    ->warning()
                    ->visible(fn () => $this->queueStalled()),
                Callout::make('Server darajasida AI o‘chirilgan')
                    ->description('.env faylida AI_ENABLED=false. Bu yerdagi sozlamalar ishlashi uchun uni true qiling.')
                    ->danger()
                    ->visible(fn () => ! config('ai.enabled')),
            ]),

            Section::make('Ulanish')
                ->description('Qaysi AI postlarni tahlil qiladi. Bu yerda saqlangan kalit .env dagidan ustun turadi va bazada shifrlangan holda saqlanadi.')
                ->schema([
                    Select::make('provider')->label('Provayder')->options(AiConfig::PROVIDERS)->required()->live()->native(false)
                        ->helperText(fn (Get $get) => match ($get('provider')) {
                            'fake' => 'Haqiqiy AI emas: oddiy kalit so‘zlar ro‘yxati bilan ishlaydi. Faqat sinov uchun.',
                            'null' => 'Postlar tahlil qilinmaydi va AI moderatsiyasi ishlamaydi.',
                            default => null,
                        }),
                    ...$this->providerFields('claude', 'Claude (Anthropic)', 'sk-ant-…', 'console.anthropic.com → API Keys', ['claude-haiku-4-5', 'claude-sonnet-5-5', 'claude-opus-5-5'],
                        'Tavsiya: claude-haiku-4-5 — tez va arzon, postlarni baholash uchun yetarli.'),
                    ...$this->providerFields('openai', 'OpenAI', 'sk-…', 'platform.openai.com → API keys', ['gpt-4o-mini'],
                        'Tavsiya: gpt-4o-mini — tez va arzon.'),
                ]),

            Section::make('Moderatsiya')
                ->description('AI baholari 0–100. Chegaradan oshgan post lentadan olinib, moderator tekshiruvini kutadi. Foydalanuvchi avtomatik jazolanmaydi.')
                ->columns(3)
                ->schema([
                    Toggle::make('ai_enabled')->label('AI tahlil yoqilgan')->columnSpan(3),
                    Toggle::make('ai_auto_moderation')->label('Xavfli postlarni avtomatik tekshiruvga yuborish')->columnSpan(3),
                    TextInput::make('toxicity_review')->label('Haqorat / nafrat chegarasi')->numeric()->integer()->minValue(1)->maxValue(100)->required()
                        ->helperText('Shundan yuqori — tekshiruvga.'),
                    TextInput::make('spam_review')->label('Spam / reklama chegarasi')->numeric()->integer()->minValue(1)->maxValue(100)->required()
                        ->helperText('Shundan yuqori — tekshiruvga.'),
                    TextInput::make('auto_category_min_quality')->label('Avto-kategoriya uchun minimal sifat')->numeric()->integer()->minValue(0)->maxValue(100)->required()
                        ->helperText('Muallif kategoriya tanlamasa, AI taklifi shu sifatdan yuqori bo‘lsa qo‘yiladi.'),
                ]),

            Section::make('Xarajat nazorati')->columns(2)->schema([
                TextInput::make('requests_per_minute')->label('Daqiqasiga so‘rovlar')->numeric()->integer()->minValue(1)->maxValue(1000)->required(),
                TextInput::make('daily_limit')->label('Kunlik so‘rovlar limiti')->numeric()->integer()->minValue(0)->required()
                    ->helperText('Limitga yetganda qolgan postlar keyingi kunga qoldiriladi.'),
            ]),
        ])->statePath('data');
    }

    /** @return list<Component> */
    private function providerFields(string $name, string $label, string $keyHint, string $where, array $models, string $modelHint): array
    {
        $visible = fn (Get $get) => $get('provider') === $name;

        return [
            Section::make($label)->compact()->visible($visible)->columns(2)->schema([
                TextInput::make("{$name}_api_key")->label('API kalit')->password()->revealable()->autocomplete('off')
                    ->placeholder(fn () => AiConfig::maskedKey($name) ? 'Saqlangan: '.AiConfig::maskedKey($name) : $keyHint)
                    ->helperText(fn () => match (AiConfig::keySource($name)) {
                        'admin' => 'Kalit admin paneldan saqlangan. Almashtirish uchun yangisini kiriting; bo‘sh qoldirilsa — o‘zgarmaydi.',
                        'env' => 'Hozir .env dagi kalit ishlatilmoqda. Bu yerga kiritsangiz — shu kalit ishlatiladi.',
                        default => "Kalit yo‘q. Olish: {$where}.",
                    })
                    ->required(fn (Get $get) => $get('provider') === $name && ! AiConfig::keySource($name))
                    ->validationMessages(['required' => 'Bu provayder uchun API kalit kerak.'])
                    ->columnSpan(2),
                Toggle::make("{$name}_forget_key")->label('Saqlangan kalitni o‘chirish')
                    ->helperText('.env dagi kalit (bo‘lsa) ishlatiladi.')
                    ->visible(fn () => AiConfig::keySource($name) === 'admin')
                    ->columnSpan(2),
                TextInput::make("{$name}_model")->label('Model')->required()->datalist($models)->helperText($modelHint),
                TextInput::make("{$name}_base_url")->label('API manzili')->url()->required()
                    ->helperText('Odatda o‘zgartirilmaydi (proksi ishlatilsa — uning manzili).'),
                TextInput::make("{$name}_price_input")->label('Narx: kiruvchi token')->numeric()->minValue(0)->prefix('$')->suffix('/ 1M')
                    ->helperText('Faqat xarajat statistikasi uchun.'),
                TextInput::make("{$name}_price_output")->label('Narx: chiquvchi token')->numeric()->minValue(0)->prefix('$')->suffix('/ 1M'),
            ]),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([
                    Action::make('save')->label('Saqlash')->submit('save'),
                    Action::make('test')->label('Ulanishni tekshirish')->icon(Heroicon::OutlinedSignal)->color('gray')
                        ->action(fn () => $this->testConnection()),
                ])]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $before = $this->auditSnapshot();

        Setting::write('ai_provider', $data['provider']);
        Setting::write('ai_enabled', (bool) $data['ai_enabled']);
        Setting::write('ai_auto_moderation', (bool) $data['ai_auto_moderation']);

        foreach (['toxicity_review', 'spam_review', 'auto_category_min_quality'] as $key) {
            $this->writeOverride("ai_{$key}", (int) $data[$key], (int) config("ai.thresholds.{$key}"));
        }
        $this->writeOverride('ai_requests_per_minute', (int) $data['requests_per_minute'], (int) config('ai.requests_per_minute'));
        $this->writeOverride('ai_daily_limit', (int) $data['daily_limit'], (int) config('ai.daily_request_limit'));

        foreach (AiConfig::REMOTE as $name) {
            if (! array_key_exists("{$name}_model", $data)) {
                continue; // tanlanmagan provayder maydonlari formada yashirin — saqlangan qiymatlari o‘zgarmaydi
            }
            foreach (AiConfig::FIELDS as $field) {
                $value = $data["{$name}_{$field}"] ?? null;
                $default = config("ai.providers.{$name}.{$field}");
                $isPrice = in_array($field, ['price_input', 'price_output'], true);
                $this->writeOverride("ai_{$name}_{$field}", $isPrice ? (float) $value : trim((string) $value), $isPrice ? (float) $default : (string) $default);
            }

            if (filled($data["{$name}_api_key"] ?? null)) {
                AiConfig::storeKey($name, $data["{$name}_api_key"]);
            } elseif ($data["{$name}_forget_key"] ?? false) {
                AiConfig::storeKey($name, null);
            }
        }

        app(AuditLogger::class)->log('ai.settings', null, $before, $this->auditSnapshot());

        $this->mount(); // kalit maydonlari tozalanadi, holat yangilanadi
        Notification::make()->title('AI sozlamalari saqlandi')->success()->send();
    }

    /** Saqlashdan oldin — formadagi (hali saqlanmagan) qiymatlar bilan bitta sinov tahlili. */
    public function testConnection(): void
    {
        $data = $this->form->getRawState();
        $name = (string) ($data['provider'] ?? 'null');

        if ($name === 'null') {
            Notification::make()->title('AI o‘chirilgan — tekshiradigan ulanish yo‘q')->warning()->send();

            return;
        }

        $started = hrtime(true);
        try {
            $result = $this->makeProvider($name, $data)->analyzePost(self::SAMPLE, Category::cachedActive()->pluck('slug')->all());
        } catch (Throwable $e) {
            Notification::make()->title('Ulanish ishlamadi')->body(mb_substr($e->getMessage(), 0, 300))->danger()->persistent()->send();

            return;
        }
        $ms = (int) ((hrtime(true) - $started) / 1_000_000);

        Notification::make()->title('Ulanish ishlayapti')
            ->body("Model: {$result->model} · javob ".Number::format($ms)." ms · sinov matni: haqorat {$result->toxicityScore}, spam {$result->spamScore}, sifat {$result->qualityScore}.")
            ->success()->persistent()->send();
    }

    private function makeProvider(string $name, array $data): AiProvider
    {
        if ($name === 'fake') {
            return new FakeProvider;
        }

        $config = AiConfig::providerConfig($name);
        foreach (['model', 'base_url'] as $field) {
            if (filled($data["{$name}_{$field}"] ?? null)) {
                $config[$field] = trim((string) $data["{$name}_{$field}"]);
            }
        }
        if (filled($data["{$name}_api_key"] ?? null)) {
            $config['api_key'] = trim((string) $data["{$name}_api_key"]);
        } elseif ($data["{$name}_forget_key"] ?? false) {
            $config['api_key'] = config("ai.providers.{$name}.api_key");
        }

        return $name === 'claude' ? new ClaudeProvider($config) : new OpenAiProvider($config);
    }

    /** .env dagi qiymatga teng bo‘lsa — bazaga yozilmaydi (keyin .env o‘zgarsa, shu ishlaydi). */
    private function writeOverride(string $key, mixed $value, mixed $default): void
    {
        Setting::write($key, $value === '' || $value == $default ? null : $value);
    }

    private function auditSnapshot(): array
    {
        $snapshot = [
            'provider' => AiConfig::provider(),
            'enabled' => (bool) Setting::read('ai_enabled'),
            'auto_moderation' => (bool) Setting::read('ai_auto_moderation'),
            'thresholds' => AiConfig::thresholds(),
            'requests_per_minute' => AiConfig::requestsPerMinute(),
            'daily_limit' => AiConfig::dailyLimit(),
        ];
        foreach (AiConfig::REMOTE as $name) {
            $config = AiConfig::providerConfig($name);
            $snapshot[$name] = [
                'model' => $config['model'] ?? null,
                'base_url' => $config['base_url'] ?? null,
                'key' => AiConfig::maskedKey($name), // kalitning o‘zi audit'ga yozilmaydi
            ];
        }

        return $snapshot;
    }

    private function statusLine(): string
    {
        $name = AiConfig::provider();
        $line = 'Hozir ishlayapti: '.AiConfig::PROVIDERS[$name];

        if (in_array($name, AiConfig::REMOTE, true)) {
            $line .= ' · model '.(AiConfig::providerConfig($name)['model'] ?? '—');
            $line .= ' · kalit: '.match (AiConfig::keySource($name)) {
                'admin' => 'admin paneldan ('.AiConfig::maskedKey($name).')',
                'env' => '.env dan ('.AiConfig::maskedKey($name).')',
                default => 'YO‘Q — tahlil ishlamaydi',
            };
        }

        $last = PostAiAnalysis::query()->latest('id')->first();

        return $line.' · oxirgi tahlil: '.($last ? $last->created_at->diffForHumans().($last->status === PostAiAnalysis::STATUS_FAILED ? ' (xato)' : '') : 'hali yo‘q');
    }

    private function queueLine(): string
    {
        $connection = (string) config('queue.default');
        if ($connection === 'sync') {
            return 'Navbat: sync — tahlil post joylanishi bilan darhol bajariladi.';
        }

        $waiting = $this->waitingJobs();

        return "Navbat: {$connection} · AI navbatida kutayotgan vazifalar: ".($waiting['count'] ?? '—')
            .(($waiting['oldest'] ?? null) ? ' (eng eskisi '.$waiting['oldest']->diffForHumans().')' : '');
    }

    private function queueStalled(): bool
    {
        $oldest = $this->waitingJobs()['oldest'] ?? null;

        return $oldest !== null && $oldest->lt(now()->subMinutes(10));
    }

    /** @return array{count?: int, oldest?: Carbon|null} */
    private function waitingJobs(): array
    {
        if (config('queue.default') !== 'database') {
            return [];
        }

        try {
            $jobs = DB::table(config('queue.connections.database.table', 'jobs'))->where('queue', config('ai.queue'));
            $oldest = (clone $jobs)->min('available_at');

            return ['count' => $jobs->count(), 'oldest' => $oldest ? now()->setTimestamp((int) $oldest) : null];
        } catch (Throwable) {
            return [];
        }
    }
}
