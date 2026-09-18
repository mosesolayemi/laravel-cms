<?php

namespace Cms\Console;

use Cms\Models\Assets\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Adds every file already on the media disk to the asset library, so what
 * the site uses today can be chosen again from any page or component.
 *
 * Safe to re-run: files already in the library are left as they are, with
 * whatever name and group an editor gave them.
 */
class ImportAssetsCommand extends Command
{
    protected $signature = 'cms:import-assets
        {--path= : Only this folder on the media disk, e.g. images/gallery}
        {--group= : Put every newly added file in this group instead of working one out}
        {--dry-run : List what would be added without adding it}';

    protected $description = 'Add the files already on the CMS media disk to the asset library';

    public function handle(): int
    {
        if (! Schema::hasTable('cms_assets')) {
            $this->error('No cms_assets table. Run: php artisan migrate');

            return self::FAILURE;
        }

        $disk = config('cms.media.disk', 'public');
        $root = trim($this->option('path') ?: config('cms.media.images', 'images'), '/');

        $files = collect(Storage::disk($disk)->allFiles($root))
            ->reject(fn (string $path): bool => Str::startsWith(basename($path), '.'))
            ->values();

        $known = Asset::query()->where('disk', $disk)->pluck('path')->flip();
        $new = $files->reject(fn (string $path): bool => $known->has($path))->values();

        $this->components->info("{$files->count()} file(s) under {$disk}:{$root}; {$new->count()} not in the library yet.");

        if ($new->isEmpty()) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['File', 'Group'], $new->map(fn (string $path): array => [$path, $this->groupFor($path)])->all());

            return self::SUCCESS;
        }

        $added = 0;
        $bar = $this->output->createProgressBar($new->count());
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  %message%');
        $bar->setMessage('starting');
        $bar->start();

        foreach ($new as $path) {
            $bar->setMessage($path);

            $asset = Asset::register($path, $disk, [
                'name' => $this->nameFor($path),
                'group' => $this->option('group') ?: $this->groupFor($path),
            ]);

            $added += $asset ? 1 : 0;
            $bar->advance();
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine(2);

        $this->components->info("{$added} file(s) added to the asset library.");

        return self::SUCCESS;
    }

    /**
     * Seeded images are copied from public/assets/images/{folder}/{file} to a
     * flat "{folder}-{file}" (see Media::import); the folder is found again
     * by checking which split names a real theme file. Anything else is
     * grouped by the folder it sits in.
     */
    protected function groupFor(string $path): string
    {
        $relative = Str::after($path, trim(config('cms.media.images', 'images'), '/') . '/');
        $folder = Str::before($relative, '/');
        $file = basename($relative);

        if ($folder !== $relative && in_array($folder, ['site', 'library'], true)) {
            $source = $this->themeFolder($file);

            if ($source) {
                return Str::headline(basename($source));
            }
        }

        return match ($folder) {
            config('cms.assets.directory', 'assets'), 'components', 'sections' => 'Uploads',
            $relative => 'Uploads',
            default => Str::headline($folder),
        };
    }

    protected function themeFolder(string $flat): ?string
    {
        $offset = 0;

        while (($dash = strpos($flat, '-', $offset)) !== false) {
            $prefix = substr($flat, 0, $dash);
            $file = substr($flat, $dash + 1);

            // "why-choose" is one folder; "gallery-vehicles" is two, nested.
            foreach (array_unique([$prefix, str_replace('-', '/', $prefix)]) as $folder) {
                if (File::exists(public_path('assets/images/' . $folder . '/' . $file))) {
                    return $folder;
                }
            }

            $offset = $dash + 1;
        }

        return null;
    }

    protected function nameFor(string $path): string
    {
        $file = pathinfo($path, PATHINFO_FILENAME);
        $folder = $this->themeFolder(basename($path));

        // Drop the folder prefix a seeded copy carries in its name.
        return Str::headline($folder ? Str::after($file, str_replace('/', '-', $folder) . '-') : $file);
    }
}
