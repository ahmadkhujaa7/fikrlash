<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Kontent';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'teg';

    protected static ?string $pluralModelLabel = 'Teglar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ko‘rinadigan nom')->required()->maxLength(50),
            TextInput::make('slug')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->rule('regex:/^[\p{L}\p{N}_]+$/u'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('posts'))
            ->columns([
                TextColumn::make('name')->label('Nomi')->prefix('#')->searchable(),
                TextColumn::make('slug')->color('gray')->searchable(),
                TextColumn::make('posts_count')->label('Postlar')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Yaratilgan')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('posts_count', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTags::route('/')];
    }
}
