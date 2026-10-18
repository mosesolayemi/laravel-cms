<?php

/**
 * Checks a freshly installed application from the inside.
 *
 * Run by tests/install.sh once cms:install has finished, against the app it
 * built. The framework is booted properly rather than driven through tinker,
 * so an exception fails the run instead of being printed as a message.
 *
 * Usage: php tests/fixtures/verify-install.php /path/to/app
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhynixMedia\Cms\Media\ImageProcessor;

$app = $argv[1] ?? null;

if ($app === null || ! is_file($app . '/vendor/autoload.php')) {
    fwrite(STDERR, "usage: php verify-install.php /path/to/laravel/app\n");

    exit(2);
}

require $app . '/vendor/autoload.php';

$laravel = require $app . '/bootstrap/app.php';
$laravel->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = [];
$checks = 0;

/**
 * Record one assertion. Anything thrown counts as a failure, since a fatal
 * from a missing class is exactly the kind of breakage this is here to catch.
 */
function check(string $name, callable $assert): void
{
    global $failures, $checks;

    $checks++;

    try {
        $detail = $assert();
    } catch (Throwable $e) {
        $failures[] = $name . ': ' . get_class($e) . ' — ' . $e->getMessage();
        printf("  FAIL  %-44s %s\n", $name, $e->getMessage());

        return;
    }

    if ($detail === false) {
        $failures[] = $name;
        printf("  FAIL  %-44s\n", $name);

        return;
    }

    printf("  ok    %-44s %s\n", $name, is_string($detail) ? $detail : '');
}

echo "\nSchema\n";

/*
 * Named rather than counted: a migration that silently stops creating one of
 * these is the failure worth catching, and a count would still pass.
 */
$tables = [
    'admins', 'cms_assets', 'cms_components', 'page_visits', 'site_settings',
    'web_blogs', 'web_blogs_category', 'web_enquiries', 'web_page_blocks',
    'web_page_groups', 'web_page_layouts', 'web_page_seo_keywords',
    'web_page_templates', 'web_visits',
];

foreach ($tables as $table) {
    check("table {$table}", fn (): bool => Schema::hasTable($table));
}

echo "\nInstall artefacts\n";

check('config/cms.php published', fn (): bool => is_file($app . '/config/cms.php'));
check('app/Cms/SiteContent.php published', fn (): bool => is_file($app . '/app/Cms/SiteContent.php'));
check('app/Cms/Components/ created', fn (): bool => is_dir($app . '/app/Cms/Components'));
check('public/storage linked', fn (): bool => is_link($app . '/public/storage'));
check('config merged from the package', fn () => config('cms.images.presets') ? 'presets: ' . implode(', ', array_keys(config('cms.images.presets'))) . '; media disk: ' . config('cms.media.disk') : false);
check('a layout was seeded', fn () => DB::table('web_page_layouts')->count() > 0 ? DB::table('web_page_layouts')->count() . ' row(s)' : false);
check('an administrator exists', fn () => DB::table('admins')->count() > 0 ? DB::table('admins')->value('email') : false);

echo "\nIntervention image handling\n";

/*
 * The reason this file exists. ImageProcessor is public API of the package,
 * so it has to work in an application that requires nothing but the package
 * itself — v1.0.0 shipped it with Intervention undeclared, and it only ever
 * ran because the first host application happened to require the library.
 */
check('Intervention is autoloadable', fn (): bool => class_exists(Intervention\Image\ImageManager::class));
check('the configured driver is installed', function () {
    $driver = config('cms.images.driver', 'gd');

    if (! extension_loaded($driver === 'imagick' ? 'imagick' : 'gd')) {
        throw new RuntimeException("php-{$driver} is not installed, so no image can be processed.");
    }

    return $driver;
});

$source = null;

check('a test photograph can be made', function () use (&$source) {
    /* Deliberately the wrong shape, so a cover-crop has something to do. */
    $image = imagecreatetruecolor(900, 300);
    imagefilledrectangle($image, 0, 0, 900, 300, imagecolorallocate($image, 10, 120, 200));

    $source = tempnam(sys_get_temp_dir(), 'cms-test-') . '.jpg';
    imagejpeg($image, $source);

    return '900x300 jpeg';
});

$stored = [];

check('store() crops to a preset and writes it', function () use (&$stored, &$source) {
    $preset = array_key_first(config('cms.images.presets'));

    $stored['single'] = app(ImageProcessor::class)->store($source, $preset, 'cms-install-test');

    return $stored['single'];
});

check('the stored file is on the media disk', function () use (&$stored) {
    $disk = Storage::disk(config('cms.media.disk', 'public'));

    if (! $disk->exists($stored['single'])) {
        throw new RuntimeException('nothing was written at ' . $stored['single']);
    }

    return $disk->size($stored['single']) . ' bytes';
});

check('the output matches the preset exactly', function () use (&$stored) {
    $preset = config('cms.images.presets')[array_key_first(config('cms.images.presets'))];
    $disk = Storage::disk(config('cms.media.disk', 'public'));

    [$width, $height, , ] = getimagesizefromstring($disk->get($stored['single']));
    $mime = getimagesizefromstring($disk->get($stored['single']))['mime'];

    if ($width !== (int) $preset['width'] || $height !== (int) $preset['height']) {
        throw new RuntimeException(
            "expected {$preset['width']}x{$preset['height']}, got {$width}x{$height}"
        );
    }

    return "{$width}x{$height} {$mime}";
});

check('storeMany() writes every preset', function () use (&$stored, &$source) {
    $presets = array_combine(
        array_keys(config('cms.images.presets')),
        array_keys(config('cms.images.presets'))
    );

    $stored['many'] = app(ImageProcessor::class)->storeMany($source, $presets, 'cms-install-test');

    $disk = Storage::disk(config('cms.media.disk', 'public'));
    $sizes = [];

    foreach ($stored['many'] as $key => $path) {
        [$width, $height] = getimagesizefromstring($disk->get($path));
        $sizes[] = "{$key} {$width}x{$height}";
    }

    return implode(', ', $sizes);
});

check('forget() removes what it stored', function () use (&$stored) {
    $paths = array_merge([$stored['single']], array_values($stored['many'] ?? []));
    $disk = Storage::disk(config('cms.media.disk', 'public'));

    app(ImageProcessor::class)->forget(...$paths);

    /* Called twice on purpose: a second pass must not raise on a missing file. */
    app(ImageProcessor::class)->forget(...$paths);

    foreach ($paths as $path) {
        if ($disk->exists($path)) {
            throw new RuntimeException($path . ' is still there');
        }
    }

    return count($paths) . ' file(s) removed';
});

if ($source !== null && is_file($source)) {
    unlink($source);
}

printf("\n%d checks, %d failed\n", $checks, count($failures));

exit($failures === [] ? 0 : 1);
