<?php

namespace PhynixMedia\Cms\Filament\Pages;

use BackedEnum;
use PhynixMedia\Cms\Facades\Cms;
use PhynixMedia\Cms\Settings\SettingsSchema;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The values shared across the whole site.
 *
 * The phone number, the email address, the offices: read on every page, owned
 * by none of them. Editing them here changes them everywhere at once, which is
 * the point — before this they were kept on the Footer screen, where a change
 * to the phone number looked like a change to the footer.
 *
 * The screen itself is generic. What it holds is declared per site in
 * `cms.settings.sections`; see SettingsSchema.
 */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'cms::filament.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 40;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * A site that declares no shared settings has nothing to show here.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return SettingsSchema::hasSections();
    }

    public function mount(): void
    {
        $values = [];

        foreach (SettingsSchema::defaults() as $key => $default) {
            $values[SettingsSchema::field($key)] = Cms::setting($key, $default);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components(SettingsSchema::components());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $values = [];

        foreach (array_keys(SettingsSchema::defaults()) as $key) {
            $values[$key] = $state[SettingsSchema::field($key)] ?? null;
        }

        Cms::putSettings($values);

        Notification::make()
            ->title('Settings updated')
            ->body('Every page that uses these values now shows the new ones.')
            ->success()
            ->send();
    }
}
