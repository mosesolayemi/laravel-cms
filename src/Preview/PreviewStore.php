<?php

namespace Cms\Preview;

use Cms\Support\Media;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Holds unsaved form state long enough to render it.
 *
 * The panel's Preview button stashes what is currently in the form under a
 * random token and frames /cms-preview/{token}, so an editor sees their
 * changes before saving — and without publishing anything.
 */
class PreviewStore
{
    public const PAGE = 'page';

    public const LAYOUT = 'layout';

    public const COMPONENT = 'component';

    /**
     * @param  array<string, mixed>  $state
     */
    public static function put(string $kind, int|string|null $recordId, array $state): string
    {
        $token = Str::random(40);

        Cache::put(static::key($token), [
            'kind' => $kind,
            'record' => $recordId,
            'state' => Media::serializable($state),
        ], now()->addMinutes(static::ttl()));

        return $token;
    }

    /**
     * @return array{kind: string, record: int|string|null, state: array<string, mixed>}|null
     */
    public static function get(string $token): ?array
    {
        return Cache::get(static::key($token));
    }

    public static function url(string $kind, int|string|null $recordId, array $state): string
    {
        return route('cms.preview.show', ['token' => static::put($kind, $recordId, $state)]);
    }

    public static function ttl(): int
    {
        return (int) config('cms.preview.ttl', 60);
    }

    protected static function key(string $token): string
    {
        return "cms.preview.{$token}";
    }
}
