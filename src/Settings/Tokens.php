<?php

namespace PhynixMedia\Cms\Settings;

use PhynixMedia\Cms\Models\Settings\Setting;

/**
 * Shared values, written into page content as `{phone}`, `{email}` and the
 * like.
 *
 * This is what makes a change on the Settings screen reach pages nobody
 * thought about when the value moved: an editor who types the phone number
 * into a paragraph has copied it, and it goes stale. A token does not — every
 * component's content passes through here on the way to its view.
 *
 * Which tokens exist is `cms.settings.tokens`, a token name to a setting key.
 * Values are escaped as they go in: the text around them is usually HTML, and
 * a company name with an ampersand in it must not break the markup.
 */
class Tokens
{
    /**
     * @return array<string, string> token => the value to write
     */
    public static function map(): array
    {
        $tokens = config('cms.settings.tokens', []);

        if ($tokens === []) {
            return [];
        }

        $defaults = SettingsSchema::defaults();
        $map = [];

        foreach ($tokens as $token => $key) {
            $value = Setting::get($key, $defaults[$key] ?? null);

            // Only scalars can be written into a sentence; a list-valued
            // setting has no single rendering, so it is not a token.
            if (is_scalar($value)) {
                $map['{' . $token . '}'] = e((string) $value);
            }
        }

        return $map;
    }

    /**
     * Replace every token in a string, or in every string of an array.
     */
    public static function apply(mixed $value): mixed
    {
        $map = static::map();

        return $map === [] ? $value : static::walk($value, $map);
    }

    /**
     * @param  array<string, string>  $map
     */
    protected static function walk(mixed $value, array $map): mixed
    {
        if (is_string($value)) {
            // Nothing to scan for on the overwhelming majority of values.
            return str_contains($value, '{')
                ? strtr($value, $map)
                : $value;
        }

        if (is_array($value)) {
            return array_map(fn ($item): mixed => static::walk($item, $map), $value);
        }

        return $value;
    }
}
