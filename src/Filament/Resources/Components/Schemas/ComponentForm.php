<?php

namespace Cms\Filament\Resources\Components\Schemas;

use Cms\Components\ComponentRegistry;
use Cms\Filament\Resources\Layouts\LayoutResource;
use Cms\Filament\Resources\Pages\PageResource;
use Cms\Filament\Support\CmsFields;
use Cms\Filament\Support\PublishedFile;
use Cms\Publishing\ComponentPublisher;
use Cms\Models\Components\Component;
use Cms\Models\Pages\Templates;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A library component: its content on the left, what it is on the right —
 * the same arrangement as the page screen.
 */
class ComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(fn (Get $get): string => app(ComponentRegistry::class)->get($get('type'))?->label() ?? 'Content')
                            ->description(fn (Get $get): ?string => app(ComponentRegistry::class)->get($get('type'))?->description())
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                ...PublishedFile::callouts(ComponentPublisher::class, 'component'),
                                Text::make('Choose a type to see its fields.')
                                    ->visible(fn (Get $get): bool => blank($get('type'))),
                                Group::make()
                                    ->statePath('data')
                                    // Rebuilt whenever the type changes.
                                    ->key(fn (Get $get): string => 'data-' . ($get('type') ?? 'none'))
                                    ->schema(fn (Get $get): array => app(ComponentRegistry::class)->get($get('type'))?->schema() ?? []),
                            ]),

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
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set, Get $get, string $operation) {
                    if ($operation === 'create' && blank($get('handle'))) {
                        $set('handle', Str::slug((string) $state));
                    }
                }),

            TextInput::make('handle')
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->maxLength(255)
                ->helperText('A stable key for developers: @cmsComponent(\'handle\') places it in any Blade view.'),

            Select::make('type')
                ->options(fn (): array => app(ComponentRegistry::class)->options())
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('data', []))
                // Changing the type of a saved component would strand its
                // data in fields the new type does not have.
                ->disabledOn('edit')
                ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Fixed once created.' : null),

            TextInput::make('group')
                ->datalist(fn (): array => Component::groups())
                ->placeholder('No group')
                ->maxLength(255)
                ->helperText('Organises the library, e.g. “Home page” or “Calls to action”. Pick an existing group or type a new one.'),

            Textarea::make('description')
                ->rows(2)
                ->helperText('Notes for editors: where it is meant to be used.'),

            CmsFields::published()
                ->helperText('Unpublished components are hidden everywhere they are placed.'),

            PublishedFile::field(ComponentPublisher::class, 'Its content here'),

            Text::make(fn (?Component $record): HtmlString => self::usages($record))
                ->visibleOn('edit'),
        ];
    }

    protected static function usages(?Component $record): HtmlString
    {
        $usages = $record?->usages() ?? collect();

        if ($usages->isEmpty()) {
            return new HtmlString('<strong>Used on:</strong> nothing yet. Place it from a page or layout with “Library component”.');
        }

        $links = $usages->map(function ($model): string {
            [$label, $url] = $model instanceof Templates
                ? [$model->title, PageResource::getUrl('edit', ['record' => $model])]
                : ["{$model->label} (layout)", LayoutResource::getUrl('edit', ['record' => $model])];

            return '<a href="' . e($url) . '" style="text-decoration:underline;">' . e($label) . '</a>';
        });

        return new HtmlString('<strong>Used on ' . $usages->count() . ':</strong> ' . $links->implode(', '));
    }
}
