<?php

namespace Cms\Models\Assets;

use Cms\Models\Components\Component;
use Cms\Models\Pages\Layouts;
use Cms\Models\Pages\Templates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One file in the asset library.
 *
 * Components store the file's path, not the asset's id, so content keeps
 * working even without the library; the library is the index editors choose
 * from, and where every upload lands.
 */
class Asset extends Model
{
    protected $table = 'cms_assets';

    protected $fillable = [
        'name',
        'group',
        'disk',
        'path',
        'mime_type',
        'size',
        'width',
        'height',
        'alt',
        'managed',
        'seeded_hash',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'managed' => 'boolean',
        ];
    }

    /**
     * Add a stored file to the library, or return the entry it already has.
     * Returns null when the file is not there, or the library is not set up
     * (the migration has not run), so callers never need to check first.
     *
     * @param  array<string, mixed>  $attributes  name, group, alt, managed
     */
    /**
     * Note that an editor has written over a file the seeder put here.
     *
     * Seeded images carry the hash of what the seeder wrote. Clearing it says
     * the file is the editor's now, which is what stops the next deploy
     * copying the repository's version back over their upload, and what stops
     * the page serving the repository's version in preference to it.
     */
    public static function claim(string $path, ?string $disk = null): void
    {
        $disk ??= config('cms.media.disk', 'public');

        if (! static::libraryExists()) {
            return;
        }

        static::query()
            ->where('disk', $disk)
            ->where('path', ltrim($path, '/'))
            ->whereNotNull('seeded_hash')
            ->update(['seeded_hash' => null]);
    }

    public static function register(string $path, ?string $disk = null, array $attributes = []): ?self
    {
        $disk ??= config('cms.media.disk', 'public');
        $path = ltrim($path, '/');

        if (! static::libraryExists() || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $asset = static::query()->firstOrNew(['disk' => $disk, 'path' => $path]);

        // A file already in the library keeps the name and group an editor
        // may have given it.
        if ($asset->exists) {
            // Except that the file itself may have just been replaced. Every
            // route an upload can take ends here, so this is the one place
            // that reliably notices, and noticing is what keeps the next
            // deploy from copying the repository's version back over it.
            if ($asset->seeded_hash !== null
                && hash('sha256', Storage::disk($disk)->get($path)) !== $asset->seeded_hash) {
                $asset->forceFill(['seeded_hash' => null])->save();
            }

            return $asset;
        }

        $asset->fill([
            'name' => Str::headline(pathinfo($path, PATHINFO_FILENAME)),
            'managed' => ! static::isExternal($path),
            ...static::describe($disk, $path),
            ...$attributes,
        ])->save();

        return $asset;
    }

    /**
     * Files in the media directories other modules own (config
     * cms.assets.external) are listed, but never deleted by the library.
     */
    public static function isExternal(string $path): bool
    {
        foreach (config('cms.assets.external', []) as $directory) {
            if (Str::startsWith(ltrim($path, '/'), trim($directory, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function describe(string $disk, string $path): array
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);
        $details = ['mime_type' => $storage->mimeType($path) ?: null, 'size' => $storage->size($path)];

        if (Str::startsWith((string) $details['mime_type'], 'image/')) {
            $size = @getimagesize($storage->path($path));

            if ($size) {
                [$details['width'], $details['height']] = $size;
            }
        }

        return $details;
    }

    public static function libraryExists(): bool
    {
        static $exists;

        return $exists ??= Schema::hasTable((new static)->getTable());
    }

    /**
     * Groups already in use, for suggesting as an editor types one.
     *
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return static::query()->whereNotNull('group')->where('group', '!=', '')
            ->distinct()->orderBy('group')->pluck('group')->all();
    }

    public function storage(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk($this->disk);
    }

    public function url(): string
    {
        return $this->storage()->url($this->path);
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function dimensions(): ?string
    {
        return $this->width && $this->height ? "{$this->width} × {$this->height}" : null;
    }

    /**
     * Pages, layouts and library components whose content holds this file's
     * path. JSON columns may store the slashes escaped, so both forms are
     * looked for.
     *
     * @return Collection<int, Templates|Layouts|Component>
     */
    public function usages(): Collection
    {
        $needles = array_unique([$this->path, str_replace('/', '\\/', $this->path)]);

        $matching = function ($query, array $columns) use ($needles) {
            $query->where(function ($query) use ($columns, $needles) {
                foreach ($columns as $column) {
                    foreach ($needles as $needle) {
                        $query->orWhere($column, 'like', '%' . $needle . '%');
                    }
                }
            });
        };

        return collect()
            ->concat(Templates::query()->tap(fn ($q) => $matching($q, ['content']))->get(['id', 'title', 'url']))
            ->concat(Component::query()->tap(fn ($q) => $matching($q, ['data']))->get(['id', 'name']))
            ->concat(Layouts::query()->tap(fn ($q) => $matching($q, array_keys(Layouts::REGIONS)))->get(['id', 'label']));
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            $asset->uuid ??= Str::uuid()->toString();
        });

        // The library owns the files it manages: removing the entry removes
        // the file. Files other modules own are left where they are.
        static::deleted(function (Asset $asset) {
            if ($asset->managed) {
                $asset->storage()->delete($asset->path);
            }
        });
    }
}
