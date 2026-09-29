<?php

namespace Cms\Settings;

use Cms\Filament\Resources\Components\ComponentResource;
use Cms\Filament\Support\CmsFields;
use Cms\Models\Components\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The Settings screen, described in config rather than in code.
 *
 * Shared values — the phone number, the email address, the offices — are read
 * all over a site but belong to none of its pages, so they live in the
 * key/value store and are edited in one place. Which values a site keeps is
 * the site's business, so `cms.settings.sections` declares them and this class
 * turns that declaration into a form.
 *
 * A field is `key => [type, label, default, ...]`, where the key is the
 * setting key templates read with `setting()`. Defaults are the wording the
 * theme shipped with, so an untouched site renders as it always did.
 */
class SettingsSchema
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function sections(): array
    {
        return config('cms.settings.sections', []);
    }

    public static function hasSections(): bool
    {
        return static::sections() !== [];
    }

    /**
     * Every key the screen owns, with the value to fall back on.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (static::sections() as $section) {
            foreach ($section['fields'] ?? [] as $key => $field) {
                $defaults[$key] = $field['default'] ?? null;
            }
        }

        return $defaults;
    }

    /**
     * Setting keys contain dots, which Livewire would read as nested state.
     */
    public static function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /**
     * The component type that draws a section's values on a page, when the
     * section declares one.
     *
     * A shared value is worth editing in one place only if it can also be
     * *shown* wherever it is wanted. A section naming a component type can be
     * added to the component library from this screen, and then placed on any
     * number of pages — the library row holds no copy of the values, so all of
     * them keep following this screen.
     *
     * @return array{type: string, handle: string, name: string, group: ?string, description: ?string}|null
     */
    public static function componentFor(string $section): ?array
    {
        $declared = static::sections()[$section]['component'] ?? null;

        if (! is_array($declared) || blank($declared['type'] ?? null)) {
            return null;
        }

        return [
            'type' => $declared['type'],
            'handle' => $declared['handle'] ?? $declared['type'],
            'name' => $declared['name'] ?? Str::headline($declared['type']),
            'group' => $declared['group'] ?? null,
            'description' => $declared['description'] ?? null,
        ];
    }

    /**
     * The library row for a section's component, if it has been added.
     */
    public static function libraryComponent(string $section): ?Component
    {
        $declared = static::componentFor($section);

        return $declared
            ? Component::query()->where('handle', $declared['handle'])->first()
            : null;
    }

    /**
     * The form, as Filament sections.
     *
     * @return array<int, Section>
     */
    public static function components(): array
    {
        $sections = [];

        foreach (static::sections() as $key => $section) {
            $fields = [];

            foreach ($section['fields'] ?? [] as $settingKey => $field) {
                $fields[] = static::component(static::field($settingKey), $field);
            }

            $sections[] = Section::make($section['heading'] ?? '')
                ->description($section['description'] ?? null)
                ->columns($section['columns'] ?? 1)
                ->headerActions(static::sectionActions((string) $key))
                ->schema($fields);
        }

        return $sections;
    }

    /**
     * Putting a section on a page.
     *
     * The same affordance a builder item has — the bookmark, "Save to the
     * component library for reuse" — so an editor meets one idea, not two.
     * Saved once, the section can be placed from any page's or layout's
     * builder, as often as wanted.
     *
     * @return array<int, Action>
     */
    protected static function sectionActions(string $section): array
    {
        if (! static::componentFor($section)) {
            return [];
        }

        return [
            static::saveToLibraryAction($section),
            static::openInLibraryAction($section),
        ];
    }

    /**
     * The bookmark: save this section to the library, once.
     *
     * The row is created with no data of its own. That is the whole point —
     * the component reads the shared values as it renders, so ten placements
     * all follow this screen instead of freezing ten copies of today's wording.
     */
    protected static function saveToLibraryAction(string $section): Action
    {
        $declared = static::componentFor($section);

        return Action::make("saveToLibrary_{$section}")
            ->label('Save to library')
            ->tooltip('Save to the component library for reuse')
            ->icon(Heroicon::OutlinedBookmarkSquare)
            ->color('gray')
            ->iconButton()
            ->hidden(fn (): bool => (bool) static::libraryComponent($section))
            ->modalHeading('Save to the component library')
            ->modalDescription('The section becomes placeable on any page or layout, as often as you like. It keeps no copy of the values: every placement shows what is on this screen, so changing them here changes them everywhere at once.')
            ->modalSubmitActionLabel('Save to library')
            ->modalIcon(Heroicon::OutlinedBookmarkSquare)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('How it is listed in the library.'),
                TextInput::make('group')
                    ->datalist(fn (): array => Component::groups())
                    ->maxLength(255)
                    ->helperText('Where it is filed in the library.'),
                Textarea::make('description')->rows(2),
            ])
            ->fillForm(fn (): array => [
                'name' => $declared['name'],
                'group' => $declared['group'],
                'description' => $declared['description'],
            ])
            ->action(function (array $data) use ($section) {
                $component = static::saveSectionToLibrary($section, $data);

                Notification::make()
                    ->success()
                    ->title('Saved to the library')
                    ->body("Place “{$component->name}” on any page or layout from its builder. Every placement follows this screen.")
                    ->send();
            });
    }

    /**
     * Put a section's component in the library, and hand it back.
     *
     * The work the bookmark does, callable on its own: the action stays a thin
     * wrapper around it, and this can be exercised without driving the panel.
     *
     * @param  array<string, mixed>  $data  name, group and description from the modal
     */
    public static function saveSectionToLibrary(string $section, array $data = []): ?Component
    {
        $declared = static::componentFor($section);

        if (! $declared) {
            return null;
        }

        // Two editors on the screen at once, or a second click before the page
        // refreshes: the section already has its row, so this is not a second
        // one. The first save's name stands.
        if ($existing = static::libraryComponent($section)) {
            return $existing;
        }

        return Component::create([
            'name' => filled($data['name'] ?? null) ? $data['name'] : $declared['name'],
            'handle' => $declared['handle'],
            'type' => $declared['type'],
            'group' => filled($data['group'] ?? null) ? $data['group'] : $declared['group'],
            'description' => filled($data['description'] ?? null) ? $data['description'] : $declared['description'],
            // No copy of the values: every placement reads them as it renders.
            'data' => [],
            'status' => 1,
        ]);
    }

    /**
     * Once it is in the library, the bookmark is replaced by a way back to it —
     * and by how many pages are showing it, which is the answer to "what does
     * changing this affect?".
     */
    protected static function openInLibraryAction(string $section): Action
    {
        return Action::make("openInLibrary_{$section}")
            ->label(function () use ($section): string {
                $used = static::libraryComponent($section)?->usages()->count() ?? 0;

                return $used === 0
                    ? 'In the library'
                    : 'In the library · on ' . $used . ' ' . Str::plural('page', $used);
            })
            ->icon(Heroicon::OutlinedBookmarkSquare)
            ->color('gray')
            ->link()
            ->tooltip('Open it in the library. Place it on as many pages as you like; each one keeps following this screen.')
            ->visible(fn (): bool => (bool) static::libraryComponent($section))
            ->action(fn () => redirect(ComponentResource::getUrl(
                'edit',
                ['record' => static::libraryComponent($section)?->getKey()],
            )));
    }

    /**
     * One field. `type` defaults to a single line of text, which is what most
     * shared values are.
     *
     * @param  array<string, mixed>  $field
     */
    protected static function component(string $name, array $field): mixed
    {
        $label = $field['label'] ?? Str::headline(Str::afterLast($name, '_'));
        $help = $field['help'] ?? null;

        $component = match ($field['type'] ?? 'text') {
            'textarea' => Textarea::make($name)->rows($field['rows'] ?? 3),
            'image' => CmsFields::image($name, $field['directory'] ?? 'site'),
            'toggle' => Toggle::make($name),
            'select' => Select::make($name)->options($field['options'] ?? []),
            'repeater' => static::repeater($name, $field),
            'email' => TextInput::make($name)->email(),
            'url' => TextInput::make($name)->url(),
            'tel' => TextInput::make($name)->tel(),
            default => TextInput::make($name),
        };

        $component = $component->label($label);

        if ($help) {
            $component = $component->helperText($help);
        }

        if ($field['required'] ?? false) {
            $component = $component->required();
        }

        if ($span = $field['span'] ?? null) {
            $component = $component->columnSpan($span);
        }

        return $component;
    }

    /**
     * A list of things there may be any number of — offices, social links.
     *
     * Rows are collapsed to a label so a long list stays readable, and the
     * delete button asks first: a row holds a whole office, and undoing the
     * click does not bring it back.
     *
     * @param  array<string, mixed>  $field
     */
    protected static function repeater(string $name, array $field): Repeater
    {
        $rows = [];

        foreach ($field['schema'] ?? [] as $key => $row) {
            $rows[] = static::component($key, $row);
        }

        $labelKey = $field['item_label'] ?? null;

        return Repeater::make($name)
            ->schema($rows)
            ->columns($field['columns'] ?? 2)
            ->defaultItems(0)
            ->reorderable()
            ->collapsible()
            ->cloneable()
            ->addActionLabel($field['add_label'] ?? 'Add row')
            ->itemLabel(fn (array $state): ?string => $labelKey
                ? ($state[$labelKey] ?? null)
                : null)
            ->deleteAction(fn (Action $action): Action => $action
                ->requiresConfirmation()
                ->modalHeading('Remove this row?')
                ->modalDescription(
                    ($field['delete_warning'] ?? 'It stops appearing anywhere on the site that shows this list.')
                    . ' Nothing changes until you save.'
                )
                ->modalSubmitActionLabel('Remove')
                ->modalIcon(Heroicon::OutlinedTrash));
    }
}
