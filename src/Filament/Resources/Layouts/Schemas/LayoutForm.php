<?php

namespace PhynixMedia\Cms\Filament\Resources\Layouts\Schemas;

use PhynixMedia\Cms\Components\ComponentType;
use PhynixMedia\Cms\Filament\Support\CmsFields;
use PhynixMedia\Cms\Filament\Support\ComponentBuilder;
use PhynixMedia\Cms\Models\Pages\Layouts;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * A layout's four regions, tabbed, beside what the layout is.
 */
class LayoutForm
{
    /**
     * Which component area each region draws its choices from.
     */
    protected const AREAS = [
        'header' => ComponentType::AREA_HEADER,
        'before_content' => ComponentType::AREA_CONTENT,
        'after_content' => ComponentType::AREA_CONTENT,
        'footer' => ComponentType::AREA_FOOTER,
    ];

    protected const HELP = [
        'header' => 'Rendered at the top of every page using this layout — typically the site header and navigation.',
        'before_content' => 'Rendered on every page, directly above the page’s own components.',
        'after_content' => 'Rendered on every page, directly below the page’s own components — a call to action, say.',
        'footer' => 'Rendered at the bottom of every page using this layout.',
    ];

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
                            ->tabs(collect(Layouts::REGIONS)
                                ->map(fn (string $label, string $region): Tab => Tab::make($label)
                                    ->badge(fn (Get $get): ?int => count($get($region) ?? []) ?: null)
                                    ->schema([
                                        ComponentBuilder::make($region, self::AREAS[$region])
                                            ->helperText(self::HELP[$region]),
                                    ]))
                                ->values()
                                ->all()),

                        Section::make()
                            ->columnSpan(['lg' => 1])
                            ->schema(self::sidebar()),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function sidebar(): array
    {
        return [
            TextInput::make('label')
                ->label('Name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set, Get $get, string $operation) {
                    if ($operation === 'create' && blank($get('layout'))) {
                        $set('layout', Str::slug((string) $state));
                    }
                }),

            TextInput::make('layout')
                ->label('Handle')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->helperText('A stable key, used when content is seeded from code.'),

            Select::make('shell')
                ->label('Document shell')
                ->options(fn (): array => self::shells())
                ->placeholder('Site default')
                ->helperText('The HTML frame — <head>, stylesheets and scripts — pages are rendered inside.'),

            Toggle::make('is_default')
                ->label('Default for new pages')
                ->helperText('New pages start with this layout. Only one layout can be the default.'),

            CmsFields::published(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function shells(): array
    {
        return array_filter([
            config('cms.rendering.shell') => 'Site default',
            ...config('cms.rendering.shells', []),
            'cms::shell' => 'Plain (package default)',
        ]);
    }
}
