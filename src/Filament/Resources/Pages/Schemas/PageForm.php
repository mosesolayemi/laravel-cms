<?php

namespace Cms\Filament\Resources\Pages\Schemas;

use Cms\Cms;
use Cms\Filament\Support\CmsFields;
use Cms\Models\Pages\SeoKeyword;
use Cms\Models\Pages\Templates;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * A page has three separable concerns — what it is, where it appears in the
 * menus, and how it presents to search engines — so they are tabbed rather
 * than stacked into one long scroll of half-empty cards.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Page')
                            ->icon('heroicon-o-document-text')
                            ->columns(2)
                            ->schema(self::pageFields()),

                        Tab::make('Menus')
                            ->icon('heroicon-o-bars-3')
                            ->schema(self::menuFields()),

                        Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->columns(2)
                            ->schema(self::seoFields()),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function pageFields(): array
    {
        return [
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                // The old API derived the slug from the title on every write;
                // keep that on create, but never silently move a live page.
                ->afterStateUpdated(function ($state, callable $set, string $operation) {
                    if ($operation === 'create') {
                        $set('url', Str::slug((string) $state));
                    }
                }),

            TextInput::make('url')
                ->label('URL slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->prefix(fn (): string => rtrim(config('app.url'), '/') . '/')
                ->helperText('Must match the Blade view name under resources/views/web/pages.'),

            Select::make('parent')
                ->label('Parent page')
                ->placeholder('Top level')
                /*
                 * Only top level pages are offered. The menus render two
                 * levels, so nesting under something that is itself nested
                 * would push this page to a third level and it would simply
                 * stop appearing — with nothing on screen to explain why.
                 * Restricting the choice makes that unreachable rather than
                 * merely discouraged, and rules out parent loops too.
                 */
                ->options(fn (?Templates $record): array => self::parentOptions($record))
                ->searchable()
                // A page that already heads a group cannot itself be nested,
                // for the same reason: its children would land on level three.
                ->disabled(fn (?Templates $record): bool => (bool) $record?->isMenuGroup())
                ->helperText(fn (?Templates $record): string => $record?->isMenuGroup()
                    ? 'This page heads a menu group, so it stays at the top level. Move its child pages elsewhere first if you need to nest it.'
                    : 'Groups this page under another in the menus. Leave as Top level for a standalone page.')
                // Stored as 0 rather than null for top level pages, which the
                // select would otherwise render as a literal "0".
                ->formatStateUsing(fn ($state) => $state ?: null)
                ->dehydrateStateUsing(fn ($state): int => (int) $state),

            CmsFields::published(),
        ];
    }

    /**
     * The pages that may be chosen as a parent.
     *
     * Only pages already at the top level, never the page itself. See the
     * comment on the parent field for why the choice is this narrow.
     *
     * @return array<int, string>
     */
    public static function parentOptions(?Templates $record = null): array
    {
        return Templates::query()
            ->where('parent', 0)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (Templates $page): array => [
                $page->getKey() => "{$page->title}  ({$page->link_path})",
            ])
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    protected static function menuFields(): array
    {
        return [
            Fieldset::make('Header menu')
                ->columns(2)
                ->schema([
                    CmsFields::flag('header_position', 'Show in header menu'),
                    TextInput::make('page_order')
                        ->label('Position')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->helperText('Lower numbers come first.'),
                    TextInput::make('menu_title')
                        ->label('Menu label')
                        ->maxLength(255)
                        ->placeholder('Uses the page title')
                        ->helperText('Only when the menu wording differs from the title — “About Us” for a page titled “Company Overview”.')
                        ->columnSpanFull(),
                ]),

            Fieldset::make('Footer menu')
                ->columns(2)
                ->schema([
                    Select::make('footer_position')
                        ->label('Column')
                        ->options(Cms::footerColumns())
                        ->default(Cms::FOOTER_NONE)
                        // Pages predating the footer columns hold null here,
                        // and a required select would refuse to save them
                        // until someone noticed and picked a value.
                        ->formatStateUsing(fn ($state) => $state ?: Cms::FOOTER_NONE)
                        ->required()
                        ->helperText('Which of the footer’s two lists this appears in.'),
                    TextInput::make('footer_order')
                        ->label('Position')
                        ->numeric()
                        ->default(0)
                        ->formatStateUsing(fn ($state): int => (int) $state)
                        ->required()
                        ->helperText('The footer orders its links separately from the header.'),
                    TextInput::make('footer_title')
                        ->label('Footer label')
                        ->maxLength(255)
                        ->placeholder('Uses the menu label, then the page title')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function seoFields(): array
    {
        return [
            TextInput::make('meta_title')
                ->maxLength(255)
                ->placeholder('Uses the page title')
                ->columnSpanFull(),

            Textarea::make('meta_description')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('seo_keyword_id')
                ->label('Shared keyword set')
                ->options(fn (): array => SeoKeyword::query()->pluck('meta_title', 'id')->all())
                ->searchable()
                ->preload()
                ->placeholder('None')
                ->columnSpanFull()
                ->helperText('Reusable meta keywords, managed under SEO Keyword Sets.'),
        ];
    }
}
