<?php

namespace PhynixMedia\Cms\Filament\Resources\Pages\Schemas;

use PhynixMedia\Cms\Cms;
use PhynixMedia\Cms\Filament\Resources\Layouts\LayoutResource;
use PhynixMedia\Cms\Filament\Support\CmsFields;
use PhynixMedia\Cms\Filament\Support\ComponentBuilder;
use PhynixMedia\Cms\Filament\Support\PublishedFile;
use PhynixMedia\Cms\Models\Pages\Layouts;
use PhynixMedia\Cms\Models\Pages\SeoKeyword;
use PhynixMedia\Cms\Models\Pages\Templates;
use PhynixMedia\Cms\Publishing\PagePublisher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The page screen: what the page is made of on the left, what the page is on
 * the right.
 *
 * The main column is tabbed — content, menu placement, search engines — so
 * each concern gets the full width instead of one long scroll of half-empty
 * cards. Title, URL, layout and publishing sit in the sidebar, visible
 * whichever tab is open.
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Tabs::make()
                            ->columnSpan(['lg' => 2])
                            ->persistTabInQueryString()
                            ->tabs([
                                Tab::make('Content')
                                    ->icon('heroicon-o-squares-plus')
                                    ->badge(fn (Get $get): ?int => count($get('content') ?? []) ?: null)
                                    ->schema(self::contentFields()),

                                Tab::make('Menus')
                                    ->icon('heroicon-o-bars-3')
                                    ->schema(self::menuFields()),

                                Tab::make('SEO')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->columns(2)
                                    ->schema(self::seoFields()),
                            ]),

                        Section::make()
                            ->columnSpan(['lg' => 1])
                            ->schema(self::pageFields()),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function contentFields(): array
    {
        return [
            ...PublishedFile::callouts(PagePublisher::class, 'page'),

            ComponentBuilder::make('content'),
        ];
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
                ->prefix('/')
                ->helperText('Where the page lives on the site. Use “home” for the home page; a slash nests it, e.g. services/festival-cover.'),

            Select::make('layout_id')
                ->label('Layout')
                ->options(fn (): array => Layouts::query()->orderByDesc('is_default')->orderBy('label')->pluck('label', 'id')->all())
                ->placeholder('No layout — page content only')
                ->default(fn (): ?int => Layouts::defaultLayout()?->getKey())
                ->helperText('The header, footer and surrounding sections this page is shown in.')
                ->hintAction(
                    Action::make('editLayout')
                        ->label('Edit layout')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Get $get): ?string => filled($get('layout_id'))
                            ? LayoutResource::getUrl('edit', ['record' => $get('layout_id')])
                            : null)
                        ->openUrlInNewTab()
                        ->visible(fn (Get $get): bool => filled($get('layout_id'))),
                ),

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

            PublishedFile::field(PagePublisher::class, 'Components (edited here)'),
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
                        ->helperText('Which of the footer’s link lists this appears in. The lists themselves are edited under Content → Footer.'),
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
