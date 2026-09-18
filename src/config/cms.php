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

        // Show the older blocks/groups editor under each page, for sites
        // whose templates still read content through pagedata().
        'legacy_blocks' => false,
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
    | Asset library
    |--------------------------------------------------------------------------
    |
    | Every upload made through the CMS is added to the asset library, where
    | it can be chosen again on any page or component.
    |
    | `directory` is where files uploaded on the Assets screen go, under
    | media.images. `external` lists media directories other modules own —
    | their files can be added to the library (cms:import-assets) and reused,
    | but deleting an asset never deletes one of their files.
    |
    */

    'assets' => [
        'directory' => 'assets',
        'external' => [
            'images/blog',
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Components
    |--------------------------------------------------------------------------
    |
    | The component types editors can place on pages and layouts.
    |
    | `core` registers the package's generic types — rich text, markdown,
    | heading, image, buttons, HTML, spacer and developer sections. Set it to
    | false to offer only the host's own.
    |
    | `types` lists the host's own ComponentType classes: anything tied to the
    | site's theme belongs there, not in the package. A host type with the
    | same key as a core one replaces it.
    |
    | `views` are the Blade views the "Developer section" component may
    | place, as view => label. Leave empty to hide that component.
    |
    */

    'components' => [
        'core' => true,
        'types' => [],
        'views' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    |
    | `shell` is the Blade view a builder page is rendered inside when its
    | layout names none. It must yield `cms_header`, `content` and
    | `cms_footer`; see the package README for the full contract. `shells`
    | adds more choices to the layout screen, as view => label.
    |
    | `classes` are the CSS classes the generic components render with, so a
    | site can put them on its own grid without overriding their views.
    |
    */

    'rendering' => [
        'shell' => 'cms::shell',
        'shells' => [],

        'classes' => [
            'section' => 'cms-section',
            'container' => 'cms-container',
            'prose' => 'cms-prose',
            'heading' => 'cms-heading__title',
            'button' => 'cms-button',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Publishing pages to Blade
    |--------------------------------------------------------------------------
    |
    | `php artisan cms:publish-page` writes a page out as a Blade file, for a
    | change the components cannot express, and the file then draws the
    | page's content. `views` is where those files go, as a view name prefix:
    | "pages" writes resources/views/pages/{url}.blade.php.
    |
    */

    'publishing' => [
        'views' => 'pages',
        // cms:publish-component writes library components here.
        'components' => 'cms-components',
    ],

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    |
    | Where previews are served from, and how long a preview of unsaved
    | changes stays available (minutes). The host's catch-all page route must
    | not swallow `path`.
    |
    */

    'preview' => [
        'path' => 'cms-preview',
        'middleware' => ['web'],
        'ttl' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seedable content
    |--------------------------------------------------------------------------
    |
    | ContentDefinition classes installed by `php artisan cms:seed-components`
    | and by CmsDatabaseSeeder. The package's own adds a blank layout; a site
    | appends its own definition of its pages, components and layouts.
    |
    */

    'content' => [
        Cms\Content\CoreContent::class,
    ],

];
