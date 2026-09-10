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
    */

    'panel' => [
        'id' => 'cms',
        'path' => env('CMS_PANEL_PATH', 'admin'),
        'brand' => env('CMS_PANEL_BRAND', env('APP_NAME', 'CMS')),
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

];
