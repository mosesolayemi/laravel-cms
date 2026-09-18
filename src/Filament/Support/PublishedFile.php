<?php

namespace Cms\Filament\Support;

use Cms\Publishing\Publisher;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The panel's side of a Blade file published from a page or component: the
 * "Drawn by" switch, the notices that explain which one the site shows, and
 * the file rename a switch makes on save — the same moves as the
 * cms:publish-* commands' --revert and --use-file.
 */
class PublishedFile
{
    /**
     * @param  class-string<Publisher>  $publisher
     */
    public static function field(string $publisher, string $contentLabel): Select
    {
        return Select::make('view')
            ->label('Drawn by')
            ->placeholder($contentLabel)
            ->options(fn (?Model $record): array => $record ? static::fileOption(app($publisher), $record) : [])
            ->live()
            ->visible(fn (?Model $record): bool => $record && app($publisher)->state($record) !== null)
            ->helperText('Switching renames the file: to *.reverted.blade.php when you choose the panel\'s content, and back when you choose the file — its edits are kept either way.');
    }

    /**
     * @param  class-string<Publisher>  $publisher
     * @return array<int, Callout>
     */
    public static function callouts(string $publisher, string $what): array
    {
        return [
            Callout::make("This {$what} is drawn by a published Blade file")
                ->description(fn (?Model $record): string => 'Its content comes from '
                    . ($record ? app($publisher)->relative(app($publisher)->path($record)) : '')
                    . ', so changes made here will not show on the site. Set “Drawn by” back to use them, or copy the file\'s data here with --sync.')
                ->warning()
                ->visible(fn (?Model $record, Get $get): bool => filled($get('view')) && $record && app($publisher)->state($record) === 'active'),

            Callout::make("A reverted Blade file for this {$what} is kept")
                ->description(fn (?Model $record): string => 'The site uses what is here. '
                    . ($record ? app($publisher)->relative(app($publisher)->revertedPath($record)) : '')
                    . ' still holds the file, with any edits made to it — choose it under “Drawn by” to use it again.')
                ->info()
                ->visible(fn (?Model $record, Get $get): bool => blank($get('view')) && $record && app($publisher)->state($record) === 'reverted'),
        ];
    }

    /**
     * Rename the file to match a "Drawn by" change just saved. On failure the
     * record is put back as it was rather than left half switched.
     */
    public static function afterSave(Model $record, Publisher $publisher): bool
    {
        if (! $record->wasChanged('view')) {
            return true;
        }

        try {
            blank($record->view) ? $publisher->revert($record) : $publisher->useFile($record);

            return true;
        } catch (RuntimeException $e) {
            $record->forceFill(['view' => $record->getPrevious()['view'] ?? null])->save();

            Notification::make()->danger()->title('Could not switch')->body($e->getMessage())->send();

            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function fileOption(Publisher $publisher, Model $record): array
    {
        return match ($publisher->state($record)) {
            'active', 'unused' => [$publisher->viewName($record) => 'Published file: ' . $publisher->relative($publisher->path($record))],
            'reverted' => [$publisher->viewName($record) => 'Published file (reverted copy): ' . $publisher->relative($publisher->revertedPath($record))],
            default => [],
        };
    }
}
