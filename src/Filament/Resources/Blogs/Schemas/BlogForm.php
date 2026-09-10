<?php

namespace Cms\Filament\Resources\Blogs\Schemas;

use Cms\Filament\Support\CmsFields;
use Cms\Models\Blogs\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Post')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('url', Str::slug((string) $state))),
                    TextInput::make('url')
                        ->label('URL slug')
                        ->required()
                        ->maxLength(255),
                    Select::make('category_id')
                        ->label('Category')
                        ->options(fn (): array => Category::query()->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    CmsFields::published(),
                    TextInput::make('caption')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    FileUpload::make('cover')
                        ->image()
                        ->disk(config('cms.media.disk'))
                        ->directory(config('cms.media.images'))
                        ->columnSpanFull(),
                    RichEditor::make('content')
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('SEO')
                ->schema([
                    TextInput::make('meta_title')->maxLength(255),
                    Textarea::make('meta_description')->rows(3),
                    Textarea::make('meta_keywords')->rows(2)->helperText('Comma separated.'),
                ]),
        ]);
    }
}
