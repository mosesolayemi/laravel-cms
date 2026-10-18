<?php

namespace PhynixMedia\Cms\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One editable piece of site chrome, addressed by key.
 *
 * Values are JSON, so a key may hold a string, a number or a whole list —
 * the social links, for instance, are a list of {platform, url} pairs.
 */
class Setting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public const CACHE_KEY = 'cms.settings';

    /**
     * Every setting, keyed. Cached, because the footer asks for these on
     * every page of the site.
     */
    public static function allSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()
            ->pluck('value', 'key')
            ->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allSettings()[$key] ?? null;

        // A key that exists but was cleared should still fall back, so the
        // footer never renders an empty address block.
        return blank($value) ? $default : $value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
