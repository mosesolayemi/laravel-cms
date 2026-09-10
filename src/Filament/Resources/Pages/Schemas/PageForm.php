<?php

namespace Cms\Filament\Resources\Pages\Schemas;

use Cms\Filament\Support\CmsFields;
use Cms\Models\Pages\Layouts;
use Cms\Models\Pages\SeoKeyword;
use Cms\Models\Pages\Templates;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // The old API derived the slug from the title on
                            // every write; keep that, but let it be overridden.
                            ->afterStateUpdated(fn ($state, callable $set) => $set('url', Str::slug((string) $state))),
                        TextInput::make('url')
                            ->label('URL slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Must match the Blade view name under resources/views/web/pages.'),
                        Select::make('layout_id')
                            ->label('Layout')
                            ->options(fn (): array => Layouts::query()->pluck('label', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('parent')
                            ->label('Parent page')
                            ->options(fn (): array => Templates::query()->pluck('title', 'id')->all())
                            ->searchable()
                            ->default(0)
                            ->helperText('Leave empty for a top-level page.')
                            ->dehydrateStateUsing(fn ($state): int => (int) $state),
                        TextInput::make('page_order')
                            ->label('Menu order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('category')
                            ->numeric()
                            ->helperText('Optional grouping id used by the theme.'),
                    ]),

                Section::make('Navigation')
                    ->columns(2)
                    ->schema([
                        CmsFields::flag('header_position', 'Show in header menu'),
                        CmsFields::flag('footer_position', 'Show in footer menu'),
                        CmsFields::published(),
                    ]),

                Section::make('SEO')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')->maxLength(255),
                        Textarea::make('meta_description')->rows(3)->columnSpanFull(),
                        Select::make('seo_keyword_id')
                            ->label('Shared SEO keyword set')
                            ->options(fn (): array => SeoKeyword::query()->pluck('meta_title', 'id')->all())
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
