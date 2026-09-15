<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Back-office authentication
    |--------------------------------------------------------------------------
    |
    | The CMS authenticates administrators on its own guard, backed by its own
    | table, so panel access is never tied to the host application's users.
    | The guard and provider are registered by CmsServiceProvider.
    |
    */

    'auth' => [
        'guard' => 'admin',
        'model' => Cms\Auth\Models\Admin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    |
    | `path` is where the panel is served from, relative to the site root.
    |
    | `resources`, `pages` and `widgets` are what the host application
    | contributes to the panel. The CMS ships the structural screens — pages,
    | blocks, layouts, administrators. Anything specific to one site, such as
    | its gallery, is that site's own content and is registered from the app.
    |
    */

    'panel' => [
        'id' => 'cms',
        'path' => env('CMS_PANEL_PATH', 'admin'),
        'brand' => env('CMS_PANEL_BRAND', env('APP_NAME', 'CMS')),

        'resources' => [],
        'pages' => [],
        'widgets' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Where uploads from the panel are written. The public site resolves these
    | paths through the cms_image() helper.
    |
    */

    'media' => [
        'disk' => 'public',
        'images' => 'images',
        'videos' => 'videos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image presets
    |--------------------------------------------------------------------------
    |
    | Named crop sizes available to uploads. Every preset is cover-cropped to
    | exactly these dimensions, so a page never receives an image of an
    | unexpected shape however it was taken.
    |
    | Presets are referenced by name, which keeps the dimensions out of the
    | resources and in one editable place.
    |
    */

    'images' => [
        'driver' => env('IMAGE_DRIVER', 'gd'),
        'format' => 'webp',
        'quality' => 82,

        'presets' => [
            'gallery' => ['width' => 1140, 'height' => 832],
            'gallery_thumb' => ['width' => 285, 'height' => 208],
        ],
    ],

];
