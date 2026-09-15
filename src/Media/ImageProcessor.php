<?php

namespace Cms\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use InvalidArgumentException;

/**
 * Crops and re-encodes uploads to a fixed set of sizes.
 *
 * Photographs arrive from phones and cameras at whatever size and aspect ratio
 * the device produced. The public pages are built for exact dimensions, so
 * every upload is cover-cropped to a named preset rather than trusting whoever
 * uploaded it to have prepared the file correctly.
 *
 * Presets are named in config/cms.php so the dimensions live in one place
 * instead of being scattered through the Filament resources.
 */
class ImageProcessor
{
    protected ?ImageManager $manager = null;

    /**
     * Built lazily rather than injected: ImageManager needs a driver argument
     * the container cannot resolve on its own.
     */
    protected function manager(): ImageManager
    {
        return $this->manager ??= new ImageManager($this->driver());
    }

    /**
     * Read a file off disk.
     *
     * intervention/image v4 renamed read() to decodePath(). v4 needs PHP 8.3
     * while composer.json still allows 8.2, so either line can be installed —
     * ask the manager which it understands rather than pinning the call to one.
     */
    protected function decode(string $source): ImageInterface
    {
        $manager = $this->manager();

        return method_exists($manager, 'decodePath')
            ? $manager->decodePath($source)
            : $manager->read($source);
    }

    /**
     * Swap in a pre-configured manager, for tests or a custom driver.
     */
    public function usingManager(ImageManager $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Crop a source file to a preset and store it on the media disk.
     *
     * @param  string  $source     absolute path to the file to process
     * @param  string  $preset     a key from config('cms.images.presets')
     * @param  string  $directory  directory on the media disk
     * @return string              the stored path, relative to the disk root
     */
    public function store(string $source, string $preset, string $directory): string
    {
        [$width, $height] = $this->preset($preset);

        $encoded = (string) $this->decode($source)
            // Phones record orientation in EXIF rather than rotating the
            // pixels, so without this a portrait photo crops sideways.
            ->orient()
            ->cover($width, $height)
            ->encode($this->encoder());

        $path = trim($directory, '/') . '/' . Str::uuid() . '.' . $this->extension();

        Storage::disk($this->disk())->put($path, $encoded, 'public');

        return $path;
    }

    /**
     * Store the same source at several presets at once.
     *
     * @param  array<string, string>  $presets  [key => preset name]
     * @return array<string, string>            [key => stored path]
     */
    public function storeMany(string $source, array $presets, string $directory): array
    {
        $paths = [];

        foreach ($presets as $key => $preset) {
            $paths[$key] = $this->store($source, $preset, $directory);
        }

        return $paths;
    }

    /**
     * Remove previously stored paths, ignoring any that are already gone.
     */
    public function forget(?string ...$paths): void
    {
        $disk = Storage::disk($this->disk());

        foreach (array_filter($paths) as $path) {
            $disk->delete($path);
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function preset(string $preset): array
    {
        $presets = config('cms.images.presets', []);

        if (! isset($presets[$preset])) {
            throw new InvalidArgumentException(
                "Unknown image preset [{$preset}]. Add it to config/cms.php."
            );
        }

        return [
            (int) $presets[$preset]['width'],
            (int) $presets[$preset]['height'],
        ];
    }

    protected function driver(): string
    {
        return match (config('cms.images.driver', 'gd')) {
            'imagick' => \Intervention\Image\Drivers\Imagick\Driver::class,
            default => \Intervention\Image\Drivers\Gd\Driver::class,
        };
    }

    protected function encoder(): EncoderInterface
    {
        $quality = (int) config('cms.images.quality', 82);

        return match ($this->extension()) {
            'jpg' => new JpegEncoder(quality: $quality),
            'png' => new PngEncoder,
            'avif' => new AvifEncoder(quality: $quality),
            default => new WebpEncoder(quality: $quality),
        };
    }

    protected function extension(): string
    {
        return match (config('cms.images.format', 'webp')) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'avif' => 'avif',
            default => 'webp',
        };
    }

    protected function disk(): string
    {
        return config('cms.media.disk', 'public');
    }
}
