# CMS package

A self-contained content management system: content models, its own
administrator guard, and a Filament back office.

The package owns everything the back office needs. A host application
registers one provider and gets a working panel; nothing about the CMS leaks
into the host's `config/auth.php`, routes or controllers.

> **Integrating this into another application?** Read
> **[INTEGRATION.md](INTEGRATION.md)** — the full reference: installation, every
> configuration key, the database schema, how a page is served, the component
> contract, settings and tokens, media, previews, publishing, seeding, every
> command, and the invariants that bite. This README is the short tour.

## Layout

```
src/
  Cms.php                  read API used by the public site
  Facades/Cms.php          Cms:: facade over the above
  helpers.php              global functions for Blade (setting(), site_text(), pagedata()…)
  Settings/                shared site settings: the config-described screen, and tokens
  CmsServiceProvider.php   migrations, config, admin guard, console commands
  Providers/
    CmsPanelProvider.php   the Filament panel; lists every resource explicitly
  Auth/
    Models/Admin.php       panel administrators, on their own guard
    Console/               auth:admin
  Components/
    ComponentType.php      what a component type is: fields + a Blade view
    ComponentRegistry.php  every type editors can place
    Types/                 the generic types the package ships
  Rendering/               builder content -> HTML, pages inside layouts
  Content/                 seedable content definitions and their installer
  Console/                 cms:seed-components
  Preview/, Http/, routes/ admin-only previews, including unsaved changes
  Support/Media.php        image paths -> URLs; theme files -> media disk
  Models/
    Components/            the component library
    Pages/                 templates, layouts, SEO, visits (+ legacy blocks/groups)
    Blogs/                 posts and categories
  Filament/
    Resources/             one directory per resource
    Support/               shared fields, the component builder field
    Actions/PreviewAction  "Preview" on create/edit screens
    Widgets/
  resources/views/         cms::page, cms::shell, generic component views
  database/
    migrations/
    seeds/                 pages, layouts, menu placement, content
  config/cms.php
```

## Install into a host app

1. Autoload it — in the host's `composer.json`:

   ```json
   "autoload": { "psr-4": { "PhynixMedia\\Cms\\": "packages/cms/src" } }
   ```

2. Register the provider in `config/app.php`:

   ```php
   PhynixMedia\Cms\CmsServiceProvider::class,
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

`PhynixMedia\Cms\Media\ImageProcessor` crops and re-encodes uploads to named presets, so a
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
  CmsDatabaseSeeder.php      calls the four below, in order
  WebPageLayoutSeeder.php    the default layout's row
  WebPageTemplateSeeder.php  the pages themselves
  MenuSeeder.php             which pages appear in which menu, and where
  CmsContentSeeder.php       what layouts and pages are made of (see
                             "Seeding content" below)
  CmsAssetSeeder.php         lists files already on the media disk as assets
                             (call it last, after anything that adds files)
  CmsSeedGuard.php           keeps the seeders that reset content to the code
                             from overwriting a live site (see below)
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

On production, once the site has pages, `WebPageLayoutSeeder`,
`WebPageTemplateSeeder` and `MenuSeeder` skip themselves (they reset page
settings and menus to the code, which would undo panel edits) and say so.
The reading is taken once at the start of a run, so a first deploy still
seeds everything. A host seeder that overwrites panel edits can opt in:

```php
if (CmsSeedGuard::protects('GalleryCategorySeeder', $this->command)) {
    return;
}
```

`CMS_SEED_OVERWRITE=true` (`cms.seeding.overwrite_live`) runs them anyway;
`CMS_SEED_MEDIA=false` (`cms.seeding.media`) skips file imports.

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
use PhynixMedia\Cms\Facades\Cms;

Cms::page('about-us');       // a page with its groups and layout
Cms::menu();                 // header menu tree
Cms::footerMenu();           // footer link columns
Cms::blogs();                // published posts
Cms::blogsPage($categoryId); // a paginated page of posts
Cms::recentBlogs(2);
Cms::categories();
Cms::setting('site.email');  // the default declared in cms.settings, if unset
Cms::recordVisit($url);
```

### In Blade

`src/helpers.php` is loaded by composer's `files` autoloader, so templates get
plain functions without importing anything:

```blade
{{ setting('site.email') }}
{{ site_text($someText) }}   {{-- resolves {phone}, {email}, … --}}
@foreach (menu() as $item) ... @endforeach
@foreach (footer_menu()['links'] ?? [] as $link) ... @endforeach
{{ cms_image($element->value, 'assets/img/fallback.jpg') }}
```

A host installing the package by path should add the file to its own
composer.json, since the root package's `files` are the ones that load:

```json
"autoload": { "files": ["packages/cms/src/helpers.php"] }
```

## Shared settings

`site_settings` is a key/value store for values read all over a site but owned
by no page — the phone number, the email address, the offices. The store, the
screen that edits them and the token substitution are package infrastructure;
*which* values a site keeps is the site's own business, so the host declares
them in `cms.settings`:

```php
'settings' => [
    'sections' => [
        'contact' => [
            'heading' => 'Contact details',
            'columns' => 2,
            'fields' => [
                'site.phone' => ['type' => 'tel', 'label' => 'Phone', 'default' => '0330 999 6666'],
                'site.offices' => [
                    'type' => 'repeater',
                    'item_label' => 'name',
                    'schema' => ['name' => [], 'address' => ['type' => 'textarea']],
                ],
            ],
        ],
    ],
    'tokens' => ['phone' => 'site.phone'],
],
```

Field types: `text` (default), `email`, `url`, `tel`, `textarea`, `image`,
`toggle`, `select`, `repeater`. The Settings screen hides itself when a host
declares no sections, so the package ships no site's keys.

`default` is the wording the theme shipped with. `setting('site.phone')` with
no fallback argument returns it when nothing is saved, so the default lives in
one place rather than in every template that reads the value.

`tokens` names the settings an editor may write into page content. With
`'phone' => 'site.phone'`, any component's text may say `{phone}`, and
`ComponentRenderer` resolves it as the page renders — so a change on the
Settings screen reaches sections written long after the value moved. Values are
HTML-escaped; list-valued settings are not tokens. `site_text()` applies the
same substitution in a hand-written view.

A host's own components should keep the "blank means global" rule: give the
component an optional field, hand the view only what was filled in, and let the
view fall back with `$phone ?? setting('site.phone')`. An editor then overrides
a value for one page without detaching it everywhere.

### Placing a section on a page

A section may name a component type, which makes it placeable:

```php
'component' => ['type' => 'offices', 'handle' => 'shared-offices', 'name' => 'Offices'],
```

The Settings screen then shows the same bookmark a builder item has — "Save to
the component library for reuse" — and filing it there makes it placeable on any
page or layout, as often as wanted.

The library row is created with **no data of its own**. A library component
normally carries its stored data; this one carries none, so it reads the shared
settings as it renders and every placement follows the Settings screen instead
of freezing a copy. A placement that should differ fills in its own fields, or
takes a copy from the library.

`SettingsSchema::saveSectionToLibrary($section, $data)` does the work, so a host
can file one from a seeder or command without driving the panel.

A host may add an intermediate layer for a piece of chrome that appears on every
page — this site's footer does, so its screen can override the shared phone
number for the footer alone:

```blade
$phone = $phone ?? (setting('footer.phone') ?: setting('site.phone'));
```

Show the shared value as the field's placeholder, so an editor can see that
leaving it blank means following Settings rather than rendering nothing.

Every helper is wrapped in `function_exists`, so an application that already
defines one of these names keeps its own.

## Content model

A **page** (`web_page_templates`) is a list of **components**, stored in its
`content` column as `[{type, data}, …]` — the shape Filament's Builder
edits. Each item is either a component held inline (this page's own copy) or
a reference to one in the **component library** (`cms_components`):
`{type: "library", data: {component_id: 5}}`. Editing a library component
changes every page that references it.

A **layout** (`web_page_layouts`) is the frame a page renders in: a *shell*
view (the `<html>`, `<head>` and assets) plus four builder regions — header,
before content, after content, footer. A page chooses a layout, or none.

`status` doubles as the publish flag across every table: the site filters on
`status = 1`, and the panel presents it as a "Published" toggle. It applies
to pages and to library components alike.

The older blocks/groups model (`web_page_blocks`, `web_page_groups`, read
through `pagedata()`) is still here for sites that use it; its screen is
hidden unless `cms.panel.legacy_blocks` is true.

## Page builder

### Component types

A component type is a class: the fields an editor fills in, and a Blade view
those values render through.

```php
use PhynixMedia\Cms\Components\ComponentType;
use Filament\Forms\Components\TextInput;

class Testimonial extends ComponentType
{
    public function key(): string { return 'testimonial'; }   // stored with every instance
    public function label(): string { return 'Testimonial'; }
    public function view(): string { return 'sections.testimonial'; }

    public function schema(): array
    {
        return [
            TextInput::make('quote')->required(),
            TextInput::make('name'),
        ];
    }
}
```

The view receives the data as variables (`$quote`, `$name`) plus `$cms`, the
`RenderContext` — the page being rendered, its layout, the region, and
whether it is a preview. Override `with()` to reshape data first, `areas()`
to restrict a type to the header or footer, `group()` and `icon()` for the
picker, and `summary()` for the collapsed item's label.

The package ships only generic types — rich text, markdown, heading, image,
buttons, HTML/embed, spacer, and "developer section" (a Blade view the host
allows in `cms.components.views`). Anything that renders one site's theme
belongs in that site and is registered from its config:

```php
// config/cms.php in the host app
'components' => [
    'types' => [App\Cms\Components\Testimonial::class],
],
```

A host type with the same key as a core one replaces it — the way to restyle,
say, rich text without forking the package. `'core' => false` drops the
generic types entirely.

The generic types render with class names from `cms.rendering.classes`, so a
site can put them on its own grid without overriding their views. To change
their markup, publish the views: `php artisan vendor:publish --tag=cms-views`.

### Layouts and the shell

Builder pages render through `cms::page`, which `@extends` the layout's shell
(or `cms.rendering.shell`, falling back to the package's bare `cms::shell`).
A shell is any view that yields:

| Yield / stack     | Filled with                                    |
|-------------------|------------------------------------------------|
| `title`           | the page's meta title, else its title          |
| `meta_description`| the page's meta description, if set            |
| `cms_header`      | the layout's header region                     |
| `content`         | before-content, the page, after-content        |
| `cms_footer`      | the layout's footer region                     |
| `@stack('cms_head')`, `@stack('cms_body_end')`, `@stack('scripts')` | whatever components push |

Components are rendered from inside `cms::page`, so a component view can
`@push` a script and the shell's stack receives it.

### Rendering from the host

```php
// in the host's page controller
$page = Cms::page($url);
abort_if(! $page || $page->status != PhynixMedia\Cms\Cms::PUBLISHED, 404);

return Cms::render($page);
```

In any Blade view, a library component can be placed by handle:

```blade
@cmsComponent('call-to-action')
{{ cms_component('call-to-action') }}
<img src="{{ cms_media($image, 'assets/img/fallback.jpg') }}">
```

### Publishing a page to Blade

For the change the components cannot express, a page can be written out to a
Blade file, which then draws its content:

```
php artisan cms:publish-page services/festival-cover   # one page (home for the home page)
php artisan cms:publish-page --all                     # every page built from components
php artisan cms:publish-page about --compact           # one line per section
php artisan cms:publish-page about --force             # replace a published or reverted file
php artisan cms:publish-page about --revert            # back to its components; file kept as about.reverted.blade.php
php artisan cms:publish-page about --use-file          # serve the kept file again, edits intact
php artisan cms:publish-page about --revert --delete
```

Reverting renames the file to `*.reverted.blade.php` (a name no view can
resolve to) and `--use-file` renames it back, so moving a page between its
file and its components never loses an edit. Only `--force` and
`--delete` remove one.

Files go under `cms.publishing.views` (a view prefix; `pages` writes
`resources/views/pages/{url}.blade.php`). Each section is written as its
data — exactly as the CMS stored it — followed by a copy of its component's
markup, variables and all:

```blade
{{-- ── 2. Intro with photo ──────────────── --}}
@php
    $__cmsData = [
        'tagline' => 'festival cover',
        'title' => 'From Small Events to <br> 80,000 Capacity a Day',
    ];
@endphp
@cmsSection('intro', $__cmsData)
    <section class="about-one section-space">
        … {{ $tagline }} … {{ $badgeNumber ?? '' }} …
    </section>
@endCmsSection
```

`@cmsSection` runs the data through the component type, so anything it works
out at render time (a gallery read from the database, an image URL) stays
live, and removes the section's variables afterwards so none leak into the
next section's `?? ''` defaults. `--compact` writes `@cmsBlock('intro',
$__cmsData)` instead: the section renders exactly as in the CMS, and markup
can be added around it. Library components are placed with
`@cmsComponent('handle')`, so they stay shared.

The file holds the page's content only: the layout's header and footer, the
title, and previews all still come from the CMS. While a file is in use the
page's components are kept but not shown; the page screen says so, and its
**Drawn by** field switches either way, renaming the file as the command
does.

### Library components: copy or link

"Copy from the library" puts an independent copy of a library component's
type and data on the page: editing it there never changes the original.
"Shared library component" links instead, so library edits show everywhere
it is linked. A linked item's menu has "Make a page copy"; any item's menu
has "Save to library". Library components carry a free-text `group`.

### Asset library

`PhynixMedia\Cms\Models\Assets\Asset` indexes every file on the media disk the CMS
uses. Image fields built with `CmsFields::image()` register their uploads
and offer "Choose from assets". The Assets screen uploads, groups and
deletes; deletion is refused while content uses the file, and files in
`cms.assets.external` directories are never removed.

```
php artisan cms:import-assets [--path=images/gallery] [--group=Gallery] [--dry-run]
```

### Publishing library components

`php artisan cms:publish-component {handle}` takes the same options as
`cms:publish-page` (`--compact`, `--force`, `--revert`, `--delete`,
`--use-file`, `--all`), writing under `cms.publishing.components`. Linked
placements show the file while it is in use. Both commands also take
`--sync`, which reads the data blocks (and section order) back into the CMS;
a data block holding anything but plain values is refused.

### Preview

Every page, layout and component screen has **Preview**: it stashes the
form's current (unsaved) state and renders it in a modal, with a link out to a
full tab. Saved records can be previewed from their table rows, including
unpublished ones. Previews are served from `/cms-preview` (`cms.preview.path`)
to signed-in administrators only, marked `noindex`, and show an explanation
wherever a section could not be rendered — where visitors would see nothing.

A host catch-all page route must leave `cms.preview.path` alone.

### Seeding content

`php artisan cms:seed-components` installs every `ContentDefinition` listed
in `cms.content`: library components, layouts and page content, each keyed
by a stable handle (the URL, for pages).

```php
use PhynixMedia\Cms\Content\Blocks;
use PhynixMedia\Cms\Content\ContentDefinition;
use PhynixMedia\Cms\Support\Media;

class SiteContent implements ContentDefinition
{
    public function components(): array
    {
        return ['cta' => ['name' => 'Call to action', 'type' => 'call-to-action', 'data' => [...]]];
    }

    public function layouts(): array
    {
        return ['default' => [
            'label' => 'Site layout',
            'is_default' => true,
            'header' => [Blocks::library('site-header')],
        ]];
    }

    public function pages(): array
    {
        return ['about' => ['layout' => 'default', 'content' => [
            Blocks::make('heading', ['heading' => 'About us']),
            Blocks::make('image', ['image' => Media::import('assets/img/team.jpg')]),
            Blocks::library('cta'),
        ]]];
    }
}
```

It is safe to re-run: anything that already exists is left as editors made
it. `--force` overwrites with the definitions; `--only=pages` (repeatable)
limits it. `Media::import()` copies a file from `public/` onto the media
disk, so seeded images can be previewed and replaced in the panel like any
upload. `CmsDatabaseSeeder` runs the same installer, so `db:seed` loads it
too.
