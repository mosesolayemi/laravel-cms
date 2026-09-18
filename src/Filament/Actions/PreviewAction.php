<?php

namespace Cms\Filament\Actions;

use Cms\Preview\PreviewStore;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * "Preview" on a create or edit screen: renders what is in the form right
 * now, saved or not, in a framed modal with a link out to a full tab.
 *
 * Uses the form's state as it would be saved — lists as plain lists, uploads
 * as paths — but without validating it or storing pending uploads, so a
 * half-finished page can be previewed without filling in every required
 * field first.
 */
class PreviewAction
{
    public static function make(string $kind): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading('Preview')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn ($livewire) => view('cms::filament.preview-frame', [
                'url' => PreviewStore::url(
                    $kind,
                    method_exists($livewire, 'getRecord') ? $livewire->getRecord()?->getKey() : null,
                    static::formState($livewire),
                ),
                'ttl' => PreviewStore::ttl(),
            ]));
    }

    /**
     * The form's values in the shape they are saved in, skipping validation.
     *
     * Raw form state is not that shape: a simple list holds each line as
     * ['text' => …], an upload as [uuid => path]. Components are written for
     * saved data, so they are handed that. Should dehydrating fail on
     * half-entered data, the raw state is the fallback.
     *
     * @return array<string, mixed>
     */
    protected static function formState(object $livewire): array
    {
        $form = $livewire->form;

        try {
            return Component::withVisibilityCache(function () use ($form, $livewire): array {
                // Dehydration converts the state it is given, so start from the
                // form's live values, as getState() does after validating.
                $path = $form->getStatePath();
                $state = [];
                $path ? data_set($state, $path, data_get($livewire, $path)) : $state = $form->getRawState();

                $form->dehydrateState($state);
                $form->mutateDehydratedState($state);

                return (array) ($path ? data_get($state, $path, []) : $state);
            });
        } catch (Throwable $e) {
            report($e);

            return $form->getRawState();
        }
    }
}
