<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Kontent';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'kategoriya';

    protected static ?string $pluralModelLabel = 'Kategoriyalar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nomi')->required()->maxLength(60)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Set $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug(str_replace(['‘', '’', 'ʻ', "'"], '', (string) $state))) : null),
            TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('description')->label('Tavsif')->maxLength(255)->columnSpanFull(),
            TextInput::make('icon')->label('Ikona nomi')->maxLength(40)->helperText('Masalan: code, book, heart'),
            TextInput::make('sort_order')->label('Tartib')->numeric()->default(0),
            Toggle::make('is_active')->label('Faol')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label('Nomi')->searchable()->description(fn (Category $r) => $r->description),
                TextColumn::make('slug')->color('gray'),
                TextColumn::make('posts_count')->label('Postlar')->numeric()->sortable(),
                TextColumn::make('sort_order')->label('Tartib')->sortable(),
                IconColumn::make('is_active')->label('Faol')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Kategoriya o‘chirilsa, undagi postlar kategoriyasiz qoladi.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
