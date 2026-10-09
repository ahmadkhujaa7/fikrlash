<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Sozlamalar';

    protected static ?string $title = 'Tizim sozlamalari';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::read($k)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brending')
                    ->description('Sayt nomi, logosi va brauzer tabidagi belgi. Logo yuklanmasa — standart “fikrlash.” so‘z belgisi ko‘rinadi.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')->label('Sayt nomi')->required()->maxLength(40)
                            ->helperText('Sahifa sarlavhalari, ijtimoiy tarmoqlardagi ulashish va admin panelda ishlatiladi.'),
                        FileUpload::make('favicon')->label('Favicon (brauzer tabi belgisi)')
                            ->image()->disk(config('fikrlash.media.disk'))->directory('branding')->visibility('public')
                            ->acceptedFileTypes(['image/png', 'image/webp'])->maxSize(512)
                            ->helperText('Kvadrat PNG, kamida 64×64 px.'),
                        FileUpload::make('logo_light')->label('Logo (yorug‘ fon uchun)')
                            ->image()->disk(config('fikrlash.media.disk'))->directory('branding')->visibility('public')
                            ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])->maxSize(1024)
                            ->helperText('Shaffof fonli PNG tavsiya etiladi; balandligi 32 px da ko‘rsatiladi (yaxshi sifat uchun 64–96 px).'),
                        FileUpload::make('logo_dark')->label('Logo (tungi rejim uchun, ixtiyoriy)')
                            ->image()->disk(config('fikrlash.media.disk'))->directory('branding')->visibility('public')
                            ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])->maxSize(1024)
                            ->helperText('Yuklanmasa — tungi rejimda ham asosiy logo ishlatiladi.'),
                    ]),
                Section::make('Bosh sahifa')->schema([
                    Textarea::make('daily_question')->label('Kun savoli')->maxLength(200)->rows(2)
                        ->placeholder('Masalan: Qaysi kitob hayotingizni o‘zgartirdi?')
                        ->helperText('Lenta tepasida ko‘rinadi va foydalanuvchilarni yozishga undaydi. Bo‘sh qoldirilsa — ko‘rsatilmaydi.'),
                ]),
                Section::make('Platforma')->schema([
                    Toggle::make('registration_open')->label('Ro‘yxatdan o‘tish ochiq'),
                    Textarea::make('announcement')->label('E’lon (barcha sahifalar tepasida)')->maxLength(300)
                        ->helperText('Bo‘sh qoldirilsa — e’lon ko‘rsatilmaydi.'),
                ]),
            ])
            ->statePath('data');
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
        $disk = Storage::disk(config('fikrlash.media.disk'));
        $old = [];

        foreach ($data as $key => $value) {
            $old[$key] = Setting::read($key);
            $value = match ($key) {
                'announcement', 'daily_question' => trim((string) $value) ?: null,
                'site_name' => trim((string) $value) ?: 'Fikrlash.uz',
                'logo_light', 'logo_dark', 'favicon' => $value ?: null,
                default => (bool) $value,
            };

            // Almashtirilgan yoki olib tashlangan logo fayli diskdan tozalanadi.
            if (in_array($key, ['logo_light', 'logo_dark', 'favicon'], true) && $old[$key] && $old[$key] !== $value) {
                $disk->delete($old[$key]);
            }

            Setting::write($key, $value);
        }

        app(AuditLogger::class)->log('settings.updated', null, $old, $data);
        Notification::make()->title('Sozlamalar saqlandi')->success()->send();
    }
}
