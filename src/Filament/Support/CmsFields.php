<?php

namespace Cms\Filament\Support;

use Filament\Forms\Components\Toggle;

/**
 * Shared form fields for the CMS tables.
 *
 * The legacy schema stores every boolean as an integer (or, for the menu
 * positions, a numeric string), so the conversion lives here once instead of
 * being repeated — and mis-repeated — in eight resources.
 */
class CmsFields
{
    /**
     * `status` doubles as the soft-delete flag across the CMS tables: the old
     * API set it to 0 rather than deleting rows, and the public site filters
     * on status = 1.
     */
    public static function published(string $name = 'status'): Toggle
    {
        return Toggle::make($name)
            ->label('Published')
            ->helperText('Unpublished records stay in the database but are hidden from the site.')
            ->default(true)
            ->dehydrateStateUsing(fn ($state): int => $state ? 1 : 0);
    }

    /**
     * The menu-position columns are strings holding "1" / "0".
     */
    public static function flag(string $name, string $label): Toggle
    {
        return Toggle::make($name)
            ->label($label)
            ->default(false)
            ->dehydrateStateUsing(fn ($state): string => $state ? '1' : '0');
    }
}
