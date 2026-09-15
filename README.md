# CMS package

A self-contained content management system: content models, its own
administrator guard, and a Filament back office.

The package owns everything the back office needs. A host application
registers one provider and gets a working panel; nothing about the CMS leaks
into the host's `config/auth.php`, routes or controllers.

## Layout

```
src/
  Cms.php                  read API used by the public site
  Facades/Cms.php          Cms:: facade over the above
  CmsServiceProvider.php   migrations, config, admin guard, console commands
  Providers/
    CmsPanelProvider.php   the Filament panel; lists every resource explicitly
  Auth/
    Models/Admin.php       panel administrators, on their own guard
    Console/               auth:admin
  Models/
    Pages/                 templates, blocks, groups, layouts, SEO, visits
    Blogs/                 posts and categories
  Filament/
    Resources/             one directory per resource
    Support/CmsFields.php  shared form fields for the legacy integer columns
    Widgets/
  database/
    migrations/
    seeds/                 pages, layouts and menu placement
  config/cms.php
```

## Install into a host app

1. Autoload it — in the host's `composer.json`:

   ```json
   "autoload": { "psr-4": { "Cms\\": "packages/cms/src" } }
   ```

2. Register the provider in `config/app.php`:

   ```php
   Cms\CmsServiceProvider::class,
   ```

3. `php artisan migrate` then `php artisan auth:admin`.

The panel is served from `/admin`; change it with `CMS_PANEL_PATH` in `.env`.

## Adding the host site's own screens

The CMS ships the structural screens — pages, blocks, layouts, administrators.
Content that belongs to one particular site does **not** live in this package:
its migration, models and Filament resources live in the application, and are
plugged into the panel from the host's `config/cms.php`:

```php
// config/cms.php in the host app
return [
    'panel' => [
        'resources' => [
            App\Filament\Resources\GalleryImages\GalleryImageResource::class,
        ],
    ],
];
```

`pages` and `widgets` work the same way. The package's own defaults are merged
*beneath* the host's config at any depth, so overriding one nested key does not
drop its siblings.

## Image processing

`Cms\Media\ImageProcessor` crops and re-encodes uploads to named presets, so a
page only ever receives images of the shape it was designed for. Presets are
defined in `config/cms.php`:

```php
'images' => [
    'driver' => env('IMAGE_DRIVER', 'gd'),  // gd | imagick
    'format' => 'webp',
    'quality' => 82,
    'presets' => [
        'gallery' => ['width' => 1140, 'height' => 832],
    ],
],
```

Use it from a Filament `FileUpload` so the file is processed before it is
stored:

```php
FileUpload::make('path')
    ->image()
    ->disk(config('cms.media.disk'))
    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => app(ImageProcessor::class)
        ->store($file->getRealPath(), 'gallery', 'images/gallery'));
```

Uploads are cover-cropped from the centre — never stretched — and `orient()` is
applied first, so portrait photographs from phones (which record their rotation
in EXIF rather than in the pixels) do not come out sideways.

## Seeding

A seeder lives wherever its table's migration lives. The CMS owns
`web_page_layouts` and `web_page_templates`, so seeding pages, layouts and
menu placement is this package's job:

```
packages/cms/src/database/seeds/
  CmsDatabaseSeeder.php      calls the three below, in order
  WebPageLayoutSeeder.php    layouts -> blade files
  WebPageTemplateSeeder.php  the pages themselves
  MenuSeeder.php             which pages appear in which menu, and where
```

They are classmap autoloaded, matching the host's own `database/seeds`, so the
host adds the folder to composer.json alongside its own:

```json
"classmap": [
    "database/seeds",
    "database/factories",
    "packages/cms/src/database/seeds"
]
```

The host then calls one seeder:

```php
// database/seeds/DatabaseSeeder.php
$this->call(CmsDatabaseSeeder::class);
```

Or run one directly:

```
php artisan db:seed --class='\MenuSeeder'
```

Note the leading backslash — Laravel prefixes a `--class` containing no
backslash with `Database\Seeders\`, which is not where these live.

Anything whose migration is in the host application — this site's gallery, for
instance — is seeded from the host's own `database/seeds`.

Each seeder checks what it needs before it runs and says what is missing:
MenuSeeder names any page it could not place rather than quietly leaving a
link out of the menu.

## Administrators

Panel access runs on the `admin` guard against the `admins` table, registered
by `CmsServiceProvider` at boot. This is deliberately *not* the host's `users`
table: site members must never be able to reach the back office.

`Admin::canAccessPanel()` also requires `is_active`, so access is revoked with
a checkbox rather than a deletion.

Create the first one with:

```
php artisan auth:admin
```

## Reading content from the host app

The site never touches the CMS models directly — it goes through the read API:

```php
use Cms\Facades\Cms;

Cms::page('about-us');       // a page with its groups and layout
Cms::menu();                 // header menu tree, three levels deep
Cms::footerMenu();
Cms::blogs();                // published posts
Cms::blogsInCategory($id);
Cms::categories();
Cms::recordVisit($url);
```

## Content model

A **page** (`web_page_templates`) has many **blocks** (a section of the page).
A block has many **groups** — one group is one repetition, e.g. a single card
in a grid of cards. A group's `element` column is a JSON array of
`{name, type, value}` objects, edited in the panel with a repeater on the
block's edit screen.

`status` doubles as the publish flag across every table: the site filters on
`status = 1`, and the panel presents it as a "Published" toggle.
