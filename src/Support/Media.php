<?php

namespace Cms\Support;

use Cms\Models\Assets\Asset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Resolves the image values components store into URLs.
 *
 * A value may be a path on the CMS media disk (what the panel's uploads
 * produce), a file under public/ (what a theme ships), or an absolute URL. The
 * panel's upload field also hands over its state as a [uuid => path] array,
 * which is what a preview of unsaved changes sees.
 */
class Media
{
    public static function url(mixed $value, ?string $fallback = null): string
    {
        $path = static::path($value);

        if ($path !== null) {
            if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
                return $path;
            }

            $path = ltrim($path, '/');

            if (static::disk()->exists($path)) {
                return static::disk()->url($path);
            }

            if (File::exists(public_path($path))) {
                return asset($path);
            }
        }

        return filled($fallback) ? asset(ltrim($fallback, '/')) : '';
    }

    /**
     * The single stored path within a value, whatever shape it arrived in.
     */
    public static function path(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->first(fn ($item): bool => is_string($item) && filled($item));
        }

        return is_string($value) && filled($value) ? $value : null;
    }

    /**
     * Copy a file shipped under public/ onto the media disk, returning its
     * disk path, so seeded components hold images the panel can show and
     * replace like any upload. Already-copied files are left alone.
     *
     * A missing source is returned unchanged; Media::url() still resolves it
     * if it turns up in public/ later.
     */
    public static function import(string $publicPath, string $directory = 'library'): string
    {
        $source = public_path(ltrim($publicPath, '/'));

        if (! File::exists($source)) {
            return $publicPath;
        }

        $target = trim(config('cms.media.images', 'images') . '/' . trim($directory, '/'), '/')
            . '/' . Str::of($publicPath)->after('assets/images/')->replace('/', '-');

        // Copy when it is missing, and again when the file in the repository
        // has changed: a deploy that updates a theme image would otherwise
        // keep serving the copy made by the first seed, since the target path
        // is derived from the source name and therefore never changes.
        $contents = File::get($source);

        if (! static::disk()->exists($target) || static::disk()->get($target) !== $contents) {
            static::disk()->put($target, $contents);
        }

        // Seeded images join the asset library, grouped by the theme folder
        // they came from ("Backgrounds", "Services"…), so they can be reused.
        Asset::register($target, config('cms.media.disk', 'public'), [
            'name' => Str::headline(pathinfo($publicPath, PATHINFO_FILENAME)),
            'group' => Str::headline(basename(dirname($publicPath))),
        ]);

        return $target;
    }

    /**
     * Unsaved uploads in a preview are Livewire temporary files, which cannot
     * be cached; swap each for a URL the browser can load it from.
     */
    public static function serializable(mixed $state): mixed
    {
        if ($state instanceof TemporaryUploadedFile) {
            try {
                return $state->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_array($state)) {
            return array_map(static::serializable(...), $state);
        }

        return is_object($state) && ! $state instanceof \JsonSerializable && ! method_exists($state, '__toString')
            ? null
            : $state;
    }

    protected static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('cms.media.disk', 'public'));
    }
}
