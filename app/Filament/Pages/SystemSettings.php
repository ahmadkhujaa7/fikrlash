<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                Section::make('Platforma')->schema([
                    Toggle::make('registration_open')->label('Ro‘yxatdan o‘tish ochiq'),
                    Textarea::make('announcement')->label('E’lon (barcha sahifalar tepasida)')->maxLength(300)
                        ->helperText('Bo‘sh qoldirilsa — e’lon ko‘rsatilmaydi.'),
                ]),
                Section::make('AI')->schema([
                    Toggle::make('ai_enabled')->label('AI tahlil yoqilgan'),
                    Toggle::make('ai_auto_moderation')->label('Xavfli postlarni avtomatik tekshiruvga yuborish')
                        ->helperText('Toksiklik yoki spam bahosi chegaradan oshsa post moderator tekshiruvini kutadi. Foydalanuvchi avtomatik jazolanmaydi.'),
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
        $old = [];

        foreach ($data as $key => $value) {
            $old[$key] = Setting::read($key);
            Setting::write($key, $key === 'announcement' ? (trim((string) $value) ?: null) : (bool) $value);
        }

        app(AuditLogger::class)->log('settings.updated', null, $old, $data);
        Notification::make()->title('Sozlamalar saqlandi')->success()->send();
    }
}
