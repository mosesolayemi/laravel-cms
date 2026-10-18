<?php

namespace PhynixMedia\Cms\Filament\Support;

use PhynixMedia\Cms\Components\ComponentRegistry;
use PhynixMedia\Cms\Components\ComponentType;
use PhynixMedia\Cms\Filament\Resources\Components\ComponentResource;
use PhynixMedia\Cms\Filament\Support\CmsFields;
use PhynixMedia\Cms\Models\Components\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The builder field pages and layouts are assembled with.
 *
 * Offers every registered component type allowed in the area, plus two ways
 * to use the component library:
 *
 *   Copy from the library — the chosen component's type and data are copied
 *   into this page as its own component. Edits here never touch the library
 *   original, so it stays reusable elsewhere. This is the usual choice.
 *
 *   Shared library component — a live link: the page shows the library
 *   component as it is now, and editing it in the library changes every page
 *   it is linked from. For things that must stay identical, such as a
 *   site-wide call to action.
 *
 * A linked item can be turned into a page copy from its item menu ("Make a
 * page copy"); an inline one can be saved to the library ("Save to library").
 */
class ComponentBuilder
{
    /**
     * The builder item type of "Copy from the library" while nothing is chosen.
     */
    public const COPY = ComponentRegistry::LIBRARY_COPY;

    public static function make(string $name, string $area = ComponentType::AREA_CONTENT): Builder
    {
        return Builder::make($name)
            ->hiddenLabel()
            ->blocks(fn (): array => static::blocks($area))
            ->addActionLabel('Add component')
            ->addBetweenActionLabel('Insert component here')
            ->blockPickerColumns(['default' => 1, 'sm' => 2])
            ->blockPickerWidth(Width::ThreeExtraLarge)
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            // Up/down buttons on each component, as well as drag and drop.
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop()
            ->extraItemActions([
                static::visibilityAction(),
                static::saveToLibraryAction($area),
                static::detachAction(),
            ])
            ->default([]);
    }

    /**
     * @return array<int, Block>
     */
    public static function blocks(string $area): array
    {
        $blocks = [static::copyBlock($area), static::libraryBlock($area)];

        foreach (app(ComponentRegistry::class)->forArea($area) as $type) {
            $blocks[] = Block::make($type->key())
                ->label(fn (?array $state): string => static::hiddenPrefix($state) . static::itemLabel($type, $state))
                ->icon($type->icon())
                ->schema([static::hiddenFlag(), ...$type->schema()]);
        }

        return $blocks;
    }

    protected static function itemLabel(ComponentType $type, ?array $state): string
    {
        $summary = $state === null ? null : $type->summary($state);

        return filled($summary) ? "{$type->label()} — {$summary}" : $type->label();
    }

    /**
     * Whether a component is hidden from the site, kept with its data. A real
     * (invisible) field, because the builder only saves fields it knows.
     */
    protected static function hiddenFlag(): Hidden
    {
        return Hidden::make(ComponentRegistry::HIDDEN)
            ->default(false)
            ->dehydrateStateUsing(fn ($state): bool => (bool) $state);
    }

    protected static function hiddenPrefix(?array $state): string
    {
        return ! empty($state[ComponentRegistry::HIDDEN]) ? '(Hidden) ' : '';
    }

    /**
     * Hide a component from the site without removing it, or show it again.
     */
    protected static function visibilityAction(): Action
    {
        $isHidden = fn (array $arguments, Builder $component): bool => ! empty($component->getRawState()[$arguments['item']]['data'][ComponentRegistry::HIDDEN] ?? false);

        return Action::make('toggleVisibility')
            ->label(fn (array $arguments, Builder $component): string => $isHidden($arguments, $component) ? 'Show on the site' : 'Hide from the site')
            ->tooltip(fn (array $arguments, Builder $component): string => $isHidden($arguments, $component)
                ? 'Hidden: kept here, not shown on the site. Click to show it again.'
                : 'Hide from the site, keeping it here to show again later')
            ->icon(fn (array $arguments, Builder $component) => $isHidden($arguments, $component) ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
            ->color(fn (array $arguments, Builder $component): string => $isHidden($arguments, $component) ? 'warning' : 'gray')
            ->action(function (array $arguments, Builder $component) use ($isHidden): void {
                $state = $component->getRawState();
                $state[$arguments['item']]['data'][ComponentRegistry::HIDDEN] = ! $isHidden($arguments, $component);
                $component->rawState($state);
            });
    }

    protected static function libraryBlock(string $area): Block
    {
        return Block::make(ComponentRegistry::LIBRARY)
            ->label(function (?array $state): string {
                $component = filled($state['component_id'] ?? null)
                    ? Component::query()->find($state['component_id'])
                    : null;

                return static::hiddenPrefix($state) . ($component ? "Shared — {$component->name}" : 'Shared library component (linked)');
            })
            ->icon(Heroicon::OutlinedLink)
            ->schema([
                static::hiddenFlag(),
                Select::make('component_id')
                    ->label('Component')
                    ->helperText('Linked: this page shows the library component as it is, and editing it in the library changes every page it is linked from. Use “Make a page copy” in this item’s menu to edit it here instead.')
                    ->options(fn (): array => static::libraryOptions($area))
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null)
                    ->hintAction(
                        Action::make('editInLibrary')
                            ->label('Edit in library')
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->url(fn (Get $get): ?string => filled($get('component_id'))
                                ? ComponentResource::getUrl('edit', ['record' => $get('component_id')])
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Get $get): bool => filled($get('component_id'))),
                    ),
            ]);
    }

    /**
     * The library group a component saved from this builder starts in: the
     * title of the page it came from ("Home"), or the layout's name. Read from
     * the form, so an unsaved page's title works too.
     */
    protected static function sourceGroup(Builder $builder): ?string
    {
        $form = $builder->getLivewire()->data ?? [];

        foreach ([$form['title'] ?? null, $form['label'] ?? null, $builder->getRecord()?->title, $builder->getRecord()?->label] as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * Picks a library component and immediately replaces itself with an
     * independent copy of it. Never saved as itself: the select is required,
     * and choosing fills the item in place.
     */
    protected static function copyBlock(string $area): Block
    {
        return Block::make(self::COPY)
            ->label('Copy from the library')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->schema([
                Select::make('component_id')
                    ->label('Component to copy')
                    ->helperText('Its content is copied into this page. Change it freely here — the library original stays as it is, ready to use elsewhere.')
                    ->options(fn (): array => static::libraryOptions($area))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Select $component): void {
                        $library = filled($state) ? Component::query()->find((int) $state) : null;
                        $builder = $component->getParentRepeater();

                        if (! $library || ! $builder instanceof Builder) {
                            return;
                        }

                        // The field sits at {builder}.{item}.data.component_id.
                        $item = Str::before(Str::after($component->getStatePath(), $builder->getStatePath() . '.'), '.');

                        static::replaceWithCopy($builder, $item, $library);
                    }),
            ]);
    }

    /**
     * Turn a linked library item into this page's own copy.
     */
    protected static function detachAction(): Action
    {
        return Action::make('makePageCopy')
            ->label('Make a page copy')
            ->tooltip('Copy the library component into this page, so it can be edited here without changing the library')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (array $arguments, Builder $component): bool => ($component->getRawState()[$arguments['item']]['type'] ?? null) === ComponentRegistry::LIBRARY)
            ->action(function (array $arguments, Builder $component): void {
                $id = $component->getRawState()[$arguments['item']]['data']['component_id'] ?? null;
                $library = filled($id) ? Component::query()->find((int) $id) : null;

                if (! $library) {
                    Notification::make()->warning()->title('Choose a library component first.')->send();

                    return;
                }

                static::replaceWithCopy($component, $arguments['item'], $library);

                Notification::make()
                    ->success()
                    ->title('Now a page copy')
                    ->body('Edit it here freely; the library component is unchanged. Save the page to keep this.')
                    ->send();
            });
    }

    /**
     * Put a copy of a library component's type and data in place of one
     * builder item, hydrated like a freshly loaded item (so image fields and
     * the like show properly).
     */
    public static function replaceWithCopy(Builder $builder, string $item, Component $library): void
    {
        $state = $builder->getRawState();
        // A hidden placement stays hidden as a copy.
        $hidden = ! empty($state[$item]['data'][ComponentRegistry::HIDDEN] ?? false);
        $state[$item] = ['type' => $library->type, 'data' => []];
        $builder->rawState($state);

        $data = $library->data ?? [];
        unset($data[ComponentRegistry::HIDDEN]);

        $builder->getChildSchema($item)?->fill([...$data, ComponentRegistry::HIDDEN => $hidden]);
    }

    /**
     * Library components usable in this area, grouped by their library group
     * (or their type, when they have none).
     *
     * @return array<string, array<int, string>>
     */
    public static function libraryOptions(?string $area = null): array
    {
        $registry = app(ComponentRegistry::class);
        $options = [];

        foreach (Component::query()->orderBy('name')->get(['id', 'name', 'type', 'group', 'status']) as $component) {
            $type = $registry->get($component->type);

            if (! $type || ($area && ! $type->allowedIn($area))) {
                continue;
            }

            $options[$component->group ?: $type->label()][$component->getKey()] = $component->name
                . ((int) $component->status === 1 ? '' : ' (unpublished)');
        }

        ksort($options);

        return $options;
    }

    /**
     * Turn an inline component into a library one, in place.
     */
    protected static function saveToLibraryAction(string $area): Action
    {
        return Action::make('saveToLibrary')
            ->label('Save to library')
            ->tooltip('Save to the component library for reuse')
            ->icon(Heroicon::OutlinedBookmarkSquare)
            ->color('gray')
            ->visible(fn (array $arguments, Builder $component): bool => ($component->getRawState()[$arguments['item']]['type'] ?? null) !== ComponentRegistry::LIBRARY)
            ->modalHeading('Save to the component library')
            ->modalDescription('The component becomes shared: this page will show the library copy, and any other page can place it too.')
            ->modalSubmitActionLabel('Save to library')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('How it is listed in the library, e.g. “Call to action — events”.'),
                CmsFields::group(
                    fn (): array => Component::groups(),
                    'Where it is filed in the library. Starts as the page (or layout) it came from; use + for a new one.',
                ),
                Textarea::make('description')->rows(2),
            ])
            ->fillForm(fn (Builder $component): array => ['group' => static::sourceGroup($component)])
            ->action(function (array $arguments, array $data, Builder $component): void {
                $key = $arguments['item'];
                $state = $component->getState();
                $type = $state[$key]['type'] ?? null;

                // Validated and dehydrated, so uploads are stored for good.
                // Hidden belongs to this page's placement, not the component.
                $itemData = $component->getItemState($key);
                $hidden = ! empty($itemData[ComponentRegistry::HIDDEN]);
                unset($itemData[ComponentRegistry::HIDDEN]);

                $library = Component::query()->create([
                    'name' => $data['name'],
                    'type' => $type,
                    'group' => filled($data['group'] ?? null) ? $data['group'] : null,
                    'data' => $itemData,
                    'description' => $data['description'] ?? null,
                    'status' => 1,
                ]);

                $state[$key] = [
                    'type' => ComponentRegistry::LIBRARY,
                    'data' => ['component_id' => $library->getKey(), ComponentRegistry::HIDDEN => $hidden],
                ];

                $component->state($state);

                Notification::make()
                    ->success()
                    ->title('Saved to the library')
                    ->body('Save this page to keep the change.')
                    ->send();
            });
    }
}
