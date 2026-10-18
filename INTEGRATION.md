# CMS package — integration and internals

A complete reference for installing this package into a Laravel application,
and for understanding how it serves pages underneath.

Written for someone — or something — arriving with no prior knowledge of the
codebase and needing to integrate it into a different project. Everything here
is stated from the source; where a rule has a reason, the reason is given,
because most of the rules exist to stop a specific failure.

Package root: `packages/cms`. PHP namespace: `PhynixMedia\Cms\`.

---

## Contents

1. [What the package is](#1-what-the-package-is)
2. [The one design rule](#2-the-one-design-rule)
3. [Requirements and installation](#3-requirements-and-installation)
4. [What the service provider does](#4-what-the-service-provider-does)
5. [Configuration reference](#5-configuration-reference)
6. [Database schema](#6-database-schema)
7. [How a page is served](#7-how-a-page-is-served)
8. [The shell contract](#8-the-shell-contract)
9. [Content model](#9-content-model)
10. [Component types](#10-component-types)
11. [The page builder](#11-the-page-builder)
12. [Shared settings and tokens](#12-shared-settings-and-tokens)
13. [Media and the asset library](#13-media-and-the-asset-library)
14. [Previews](#14-previews)
15. [Publishing to Blade files](#15-publishing-to-blade-files)
16. [Seeding content](#16-seeding-content)
17. [Console commands](#17-console-commands)
18. [Blade helpers and directives](#18-blade-helpers-and-directives)
19. [The read API](#19-the-read-api)
20. [Blog, enquiries, visits, SEO](#20-blog-enquiries-visits-seo)
21. [The admin panel](#21-the-admin-panel)
22. [Integration checklist](#22-integration-checklist)
23. [Invariants and things that bite](#23-invariants-and-things-that-bite)
24. [Source map](#24-source-map)

---

## 1. What the package is

A self-contained CMS for a Laravel application:

- **Content models** — pages, layouts, a component library, settings, assets,
  blog, enquiries, visit counts.
- **Its own admin guard** — administrators live in `admins`, on a separate
  `admin` guard. The host's `users` table is never touched.
- **Its own Filament panel** — registered by the package, not the host.
- **A rendering pipeline** — turns builder content into HTML inside the host's
  own markup.
- **A read API** — `Cms::page()`, `Cms::render()`, `Cms::menu()` and friends,
  plus Blade helpers.

The host application supplies: the theme (markup, CSS, JS), its own component
types, a route to serve pages, and a shell view.

---

## 2. The one design rule

**Nothing in `packages/cms` may know about a particular site.**

No site's markup, CSS classes, copy, image paths or section names belong in the
package. Those live in the host application and are plugged in through
`config/cms.php`:

| Site-specific thing | Where it goes |
| --- | --- |
| Theme sections (hero, team, FAQ…) | `App\Cms\Components\*`, listed in `cms.components.types` |
| Extra panel screens | `cms.panel.resources`, `cms.panel.pages`, `cms.panel.widgets` |
| Which shared settings exist | `cms.settings.sections` |
| The site's own page/layout content | A `ContentDefinition`, listed in `cms.content` |
| Document shell | `cms.rendering.shell` |

If you find yourself adding a site's class name to the package, the extension
point you want already exists — look in `src/config/cms.php` first.

---

## 3. Requirements and installation

```json
"require": {
    "php": "^8.2",
    "filament/filament": "^5.0",
    "illuminate/support": "^12.0"
}
```

### Install as a path repository

In the **host application's** `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/cms", "options": { "symlink": true } }
    ],
    "require": {
        "ambulance/cms": "*"
    }
}
```

### Load the helpers from the host

This one is easy to miss and breaks every template at once. Composer only
autoloads the `files` entry of the **root** package, so a path-installed
package's `helpers.php` is never loaded. Add it to the host's own autoload:

```json
"autoload": {
    "files": ["packages/cms/src/helpers.php"]
}
```

Then `composer dump-autoload`.

### Provider registration

Nothing to do: `extra.laravel.providers` in the package manifest registers
`PhynixMedia\Cms\CmsServiceProvider` by package discovery, and aliases the `Cms` facade.

If the host disables discovery, add `PhynixMedia\Cms\CmsServiceProvider` to
`bootstrap/providers.php` (Laravel 11+) or `config/app.php` yourself.

### Finish

```bash
php artisan migrate
php artisan storage:link      # the media disk is served through public/storage
php artisan auth:admin        # create the first administrator
```

Browse to `/admin` (configurable; see `cms.panel.path`).

---

## 4. What the service provider does

`src/CmsServiceProvider.php`.

### register()

| Step | Detail |
| --- | --- |
| Deep-merges config | `mergeConfigDeeply()` — **not** Laravel's `mergeConfigFrom`, which only merges the top level. A host setting one nested key (`panel.resources`) would otherwise silently drop every sibling default. |
| Singletons | `Cms`, `ComponentRenderer`, `PageRenderer` |
| Component registry | Core types first, then `cms.components.types`. **A host type registered under the same key replaces the package's**, which is how a site re-skins `rich-text` without forking it. |
| Registers the panel | `CmsPanelProvider` is registered by the package, so the host's provider list stays clean and the CMS cannot be installed half-wired. |

### boot()

- Loads migrations from `src/database/migrations`
- Loads views under the `cms::` namespace
- Loads `src/routes/web.php` (preview routes)
- Registers Blade directives (§18)
- **Registers the `admin` auth guard and provider in code**, rather than asking
  the host to edit `config/auth.php` — so the package cannot be installed in a
  half-configured state, and the host's own guards are untouched
- Publish tags: `cms-config` (the config file), `cms-views` (the views)
- Registers console commands, only when running in console

---

## 5. Configuration reference

Package defaults: `src/config/cms.php`. Publish with
`php artisan vendor:publish --tag=cms-config`, or create `config/cms.php` in the
host with **only the keys being changed** — the deep merge keeps the rest.

### auth

| Key | Default | Meaning |
| --- | --- | --- |
| `auth.guard` | `admin` | Guard name the panel and previews authenticate against |
| `auth.model` | `PhynixMedia\Cms\Auth\Models\Admin` | Administrator model |

### panel

| Key | Default | Meaning |
| --- | --- | --- |
| `panel.id` | `cms` | Filament panel id |
| `panel.path` | `admin` (env `CMS_PANEL_PATH`) | URL prefix. **Must be excluded from the host's catch-all page route.** |
| `panel.brand` | `APP_NAME` (env `CMS_PANEL_BRAND`) | Sidebar/login brand name |
| `panel.resources` | `[]` | Host Filament resources to add |
| `panel.pages` | `[]` | Host Filament pages to add |
| `panel.widgets` | `[]` | Host dashboard widgets to add |
| `panel.legacy_blocks` | `false` | Show the old blocks/groups editor (see §9) |
| `panel.logo` / `logo_dark` | `null` | Path under `public/` or a URL |
| `panel.logo_height` | `2.25rem` | |
| `panel.primary` | `null` | Accent colour, hex string |
| `panel.favicon` | `null` | Path under `public/` or a URL |

### media

| Key | Default | Meaning |
| --- | --- | --- |
| `media.disk` | `public` | Filesystem disk uploads are written to |
| `media.images` | `images` | Directory prefix for images on that disk |
| `media.videos` | `videos` | Same, for video |

### footer

`footer.columns` — the link columns a fresh site starts with, each
`['key', 'title', 'highlight']`. Once an editor saves the footer screen, the
stored list wins. `key` is what a page stores in `footer_position`, so it must
not change once pages point at it.

### settings

`settings.sections` describes the **Settings** screen (§12); `settings.tokens`
maps a token name to a setting key. Both empty by default — the screen hides
itself until the host declares something.

### assets

| Key | Default | Meaning |
| --- | --- | --- |
| `assets.directory` | `assets` | Where Assets-screen uploads go, under `media.images` |
| `assets.external` | `['images/blog']` | Directories other modules own. Their files can be listed and reused, but deleting an asset never deletes one of their files. |

### images

`images.driver` (`gd`/`imagick`), `images.format` (`webp`), `images.quality`,
and `images.presets` — named crop sizes, e.g.
`'gallery' => ['width' => 1140, 'height' => 832]`. Every preset is
cover-cropped to exactly those dimensions, so a page never receives an image of
an unexpected shape.

### components

| Key | Default | Meaning |
| --- | --- | --- |
| `components.core` | `true` | Register the package's generic types |
| `components.types` | `[]` | The host's own `ComponentType` classes |
| `components.views` | `[]` | `view => label` map the "Developer section" component may place; empty hides that component |

### rendering

| Key | Default | Meaning |
| --- | --- | --- |
| `rendering.shell` | `cms::shell` | Default document shell (§8) |
| `rendering.shells` | `[]` | Extra choices on the layout screen, `view => label` |
| `rendering.classes` | see file | CSS classes the generic components render with, so a site can fit them to its own grid without overriding views |

### publishing

| Key | Default | Meaning |
| --- | --- | --- |
| `publishing.views` | `pages` | View prefix `cms:publish-page` writes to |
| `publishing.components` | `cms-components` | Same, for `cms:publish-component` |

### preview

| Key | Default | Meaning |
| --- | --- | --- |
| `preview.path` | `cms-preview` | **Must not be swallowed by the host's catch-all route** |
| `preview.middleware` | `['web']` | |
| `preview.ttl` | `60` | Minutes an unsaved-changes preview stays valid |

### seeding

| Key | Default | Meaning |
| --- | --- | --- |
| `seeding.media` | `true` (env `CMS_SEED_MEDIA`) | Whether `db:seed` imports files |
| `seeding.overwrite_live` | `false` (env `CMS_SEED_OVERWRITE`) | Run the destructive seeders on a live site anyway (§16) |

### content

`cms.content` — `ContentDefinition` classes installed by
`cms:seed-components` and `CmsDatabaseSeeder`. Defaults to
`[PhynixMedia\Cms\Content\CoreContent::class]`, which adds one blank layout.

---

## 6. Database schema

Every table the package creates, with what it is for.

| Table | Holds |
| --- | --- |
| `web_page_templates` | **Pages.** The central table. |
| `web_page_layouts` | **Layouts** — the regions drawn around a page |
| `cms_components` | **Library components** — reusable sections |
| `cms_assets` | **Asset library** — every uploaded or seeded file |
| `site_settings` | Key/value store for shared values |
| `admins` | Back-office administrators (`admin` guard) |
| `web_blogs`, `web_blogs_category` | Blog posts and categories |
| `web_enquiries` | Submitted enquiry forms |
| `page_visits` | Per-page, per-day/month/year visit counters |
| `web_visits` | Legacy visit table |
| `web_page_seo_keywords` | SEO keyword sets a page can point at |
| `web_page_blocks`, `web_page_groups` | **Legacy** content system (§9) |

### web_page_templates (pages)

| Column | Purpose |
| --- | --- |
| `url` | Unique. The path, without a leading slash. `home` is the home page. |
| `title`, `menu_title`, `footer_title` | Display name; the menu and footer may word a link differently |
| `meta_title`, `meta_description` | `<title>` falls back to `title` |
| `layout_id` | Which layout wraps it |
| `content` | **JSON** — the builder's list of `{type, data}` items |
| `view` | Set by `cms:publish-page`; when the file exists it draws the page |
| `status` | `1` published. **The public gate.** |
| `parent`, `page_order` | Menu nesting and ordering |
| `header_position` | `'1'` to appear in the header menu |
| `footer_position`, `footer_order` | Footer column key and position |
| `seo_keyword_id`, `category`, `uuid` | |

### cms_components (library)

`name`, `handle` (unique, URL-safe), `type` (a registered component key),
`group`, `data` (JSON), `view` (set by publishing), `description`, `status`.

### cms_assets

`name`, `group`, `disk`, `path` (unique together with `disk`), `mime_type`,
`size`, `width`, `height`, `alt`, `managed`, `seeded_hash`.

`seeded_hash` records the fingerprint of an image the seeder wrote, so the code
can tell "still the theme's file" from "an editor replaced it" (§13).

---

## 7. How a page is served

```
request  →  host route  →  PageController  →  Cms::page($url)
                                           →  status check (404 if not 1)
                                           →  Cms::recordVisit($url)
                                           →  Cms::render($page)
                                                 ↓
                                           PageRenderer::view()
                                                 ↓
                                     view('cms::page') @extends($shell)
```

### The host route

The package deliberately registers **no** catch-all. The host owns its URL
space. A typical arrangement:

```php
// routes/web.php — after every explicit route
Route::get('/', [PageController::class, 'show']);
Route::get('/{page}', [PageController::class, 'show'])->where('page', $reserved);
Route::get('/{page}/{sub}', [PageController::class, 'show'])->where('page', $reserved);
Route::get('/{page}/{sub}/{extra}', [PageController::class, 'show'])->where('page', $reserved);
```

`$reserved` is a regex excluding anything the app serves itself — and it **must
exclude `cms.panel.path` and `cms.preview.path`**, or the panel and previews
become 404 pages.

### The controller

```php
$template = Cms::page($url);

if (! $template || (int) $template->status !== CmsApi::PUBLISHED) {
    abort(404);
}

Cms::recordVisit($template->url);

return Cms::render($template);
```

The status check is the public gate. An unpublished page stays reachable to
editors through the panel's preview, never to visitors. A layout's own status
does **not** replace it.

### Rendering

`PageRenderer::view()`:

1. Resolves the layout (the page's own, one passed in, or `false` for none).
2. Builds a `RenderContext` (page, layout, preview flag, area, recursion trail).
3. **Renders every region up front**, before the page view starts.
4. Returns `view('cms::page', [...])`.

Regions handed to the view: `header`, `before`, `content`, `after`, `footer`.

**Why regions render first.** When any Blade view throws, Laravel wipes all of
Blade's in-progress state — the page's open sections included — so one broken
component rendered mid-page would take the whole page down. Rendered up front,
a failure costs only what was rendered so far, and `renderRegions()` simply
renders again after `flushState()`.

### Component failures

`ComponentRenderer` never lets one component break a page:

- On the live site a component that throws is `report()`ed and skipped.
- In a preview, or with `APP_DEBUG` outside preview, the problem is shown in
  place so an editor can see which section needs attention.
- Failures are memoised by fingerprint, so the second render pass does not
  re-run a component that already threw.

---

## 8. The shell contract

A **shell** is the host's document skeleton. `cms::page` extends it. Any view
may be a shell provided it yields:

| Yield / stack | Required | Purpose |
| --- | --- | --- |
| `@yield('cms_header')` | yes | Layout header region |
| `@yield('content')` | yes | Page content |
| `@yield('cms_footer')` | yes | Layout footer region |
| `@yield('title')` | no | Page title |
| `@yield('meta_description')` | no | |
| `@stack('cms_head')` | no | What components push into `<head>` |
| `@stack('cms_body_end')` | no | End of body; the preview bar uses it |
| `@stack('scripts')` | no | |

The package ships a minimal one at `cms::shell`. A real site points
`cms.rendering.shell` at its own, e.g. `web.shell`, carrying its `<head>`,
stylesheets and scripts.

Removing one of the three required yields makes the corresponding region vanish
silently — a common cause of "the header disappeared".

---

## 9. Content model

### Pages

A page's `content` column is a JSON list of builder items. Each is one of:

```php
['type' => 'rich-text', 'data' => [...]]                       // inline
['type' => 'library',   'data' => ['component_id' => 5]]       // by reference
['type' => 'library-copy', 'data' => [...]]                    // placeholder
```

| Constant | Value | Meaning |
| --- | --- | --- |
| `ComponentRegistry::LIBRARY` | `library` | Reference. Editing the library component changes every page using it. |
| `ComponentRegistry::LIBRARY_COPY` | `library-copy` | "Copy from the library" before a component is chosen |
| `ComponentRegistry::HIDDEN` | `_hidden` | A key inside `data`; the item is kept but not rendered |

A hidden item renders nothing for visitors and a slim marker in previews, so an
editor can see it is still there.

### Layouts

`Layouts::REGIONS`:

```php
['header' => 'Header', 'before_content' => 'Before content',
 'after_content' => 'After content', 'footer' => 'Footer']
```

Each region is its own builder list, cast to `array`. A layout also carries
`shell` (which document shell to use) and `is_default` (what new pages start
with).

### Menus

- **Header menu** — published pages with `header_position = '1'`, nested by
  `parent`, ordered by `page_order`, to a maximum of three levels. Nesting is
  explicit, not derived from URL structure: the header groups pages that are
  unrelated by path.
- **Footer menu** — published pages with a `footer_position` other than `none`,
  grouped by that key, ordered by `footer_order` then `page_order`.

### Legacy blocks

`web_page_blocks` / `web_page_groups` are an older content system read through
the `pagedata()` helper, kept for sites whose templates still use it.

- The **Blocks resource** is registered but sets
  `$shouldRegisterNavigation = false`, so it never appears in the sidebar — it
  is reachable only by URL.
- `cms.panel.legacy_blocks` controls something narrower: whether each page's
  edit screen carries the **blocks relation manager**
  (`PageResource::getRelations()`).

**New work should use the builder**, not this.

---

## 10. Component types

A component type is a PHP class describing one kind of section: its form
fields, its Blade view, and how it appears in the panel.

### The contract

`PhynixMedia\Cms\Components\ComponentType` (abstract):

```php
abstract public function key(): string;      // stable; stored in page data
abstract public function label(): string;    // shown in the panel
abstract public function schema(): array;    // Filament form components
abstract public function view(): string;     // Blade view to render
```

Overridable:

| Method | Default | Purpose |
| --- | --- | --- |
| `description()` | `null` | Note for editors |
| `icon()` | see class | Heroicon name |
| `group()` | see class | Groups the type in the picker |
| `areas()` | `null` (all) | Which regions it may be placed in |
| `allowedIn($area)` | derived | |
| `isAvailable()` | `true` | Hide a type whose prerequisites are missing |
| `summary($data)` | `null` | One line in the collapsed builder row |
| `with($data, $context)` | `$data` | Shape the data before the view sees it |
| `render($data, $context)` | renders `view()` | |

Areas: `AREA_CONTENT`, `AREA_HEADER`, `AREA_FOOTER`.

### Writing one

```php
namespace App\Cms\Components;

use PhynixMedia\Cms\Components\ComponentType;
use Filament\Forms\Components\TextInput;

class Hero extends ComponentType
{
    public function key(): string   { return 'hero'; }
    public function label(): string { return 'Hero banner'; }

    public function schema(): array
    {
        return [TextInput::make('title')->required()];
    }

    public function view(): string  { return 'web.sections.hero'; }
}
```

Register it:

```php
// config/cms.php
'components' => ['types' => [App\Cms\Components\Hero::class]],
```

The view receives whatever `with()` returns, plus `$cms` (the `RenderContext`).

**Replacing a core type:** register a host type with the same `key()`. The
registry registers core types first, so the host's wins.

### Core types

`rich-text`, `markdown`, `heading`, `image`, `buttons`, `html`, `spacer`,
`blade-view`. Disable them all with `cms.components.core => false`.

They render with the classes in `cms.rendering.classes`, reachable in a view
through `cms_class('section')` and so on.

---

## 11. The page builder

`PhynixMedia\Cms\Filament\Support\ComponentBuilder::make($name, $area)` returns the
Filament `Builder` field used on pages and layouts.

Each builder item has:

- **Hide** — sets `_hidden` in the item's data, keeping it without rendering it
- **Save to library** — turns an inline component into a library one *in place*:
  a new `cms_components` row is created with the item's data, and the item is
  replaced by a `library` reference. Asks for name, group and description.
- **Copy from the library** — the opposite; detaches a reference into an
  editable copy
- Duplicate, reorder, delete

`ComponentBuilder::libraryOptions($area)` lists library components for the
picker, grouped by `group` (falling back to the type's label), marking
unpublished ones.

---

## 12. Shared settings and tokens

### The store

`site_settings` is key/value with JSON values. `Setting::allSettings()` is
cached forever under `cms.settings`; **every write clears that cache**. Writing
to the table outside the model (raw SQL, a mass `update()`) needs
`Setting::flush()`, or the site keeps serving the old value.

### The screen

The Settings screen is generic and lives in the package
(`PhynixMedia\Cms\Filament\Pages\SiteSettings`). What it holds is declared per site:

```php
// config/cms.php
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
                    'default' => [...],
                ],
            ],
            // Optional: makes the section placeable on pages
            'component' => [
                'type' => 'contact-details',
                'handle' => 'shared-contact-details',
                'name' => 'Contact details',
                'group' => 'Shared',
            ],
        ],
    ],
    'tokens' => ['phone' => 'site.phone', 'email' => 'site.email'],
],
```

Field types: `text` (default), `email`, `url`, `tel`, `textarea`, `image`,
`toggle`, `select`, `repeater`.

`default` is the wording the theme shipped with. **`setting('site.phone')` with
no second argument returns that declared default when nothing is saved**, so
the fallback lives in one place instead of in every template. Defaults are not
written to the database merely by being read.

### Placing a section on a page

A section naming a `component` gains a bookmark — *"Save to the component
library for reuse"* — on the Settings screen. Saving it creates a library row
**with `data => []`**, deliberately: the component reads the shared values as
it renders, so ten placements all follow the Settings screen rather than
freezing ten copies. `SettingsSchema::saveSectionToLibrary()` does the work and
can be called from a seeder.

### Tokens

With `'phone' => 'site.phone'` declared, an editor may write `{phone}` into any
component's text. `ComponentRenderer::renderType()` passes every component's
data through `PhynixMedia\Cms\Settings\Tokens::apply()` on the way to its view, so this
works for sections written long after the value moved.

Values are HTML-escaped. Only scalar settings are tokens — a list-valued key
has no single rendering and is left alone. `site_text()` applies the same
substitution inside a hand-written view.

---

## 13. Media and the asset library

### Resolving a stored image

`PhynixMedia\Cms\Support\Media::url($value, $fallback)`, reached from Blade as
`cms_media()`. Order:

1. **Explicitly cleared** (`''` or `[]`) → return `''`. An editor who empties an
   image field means it; the fallback is only for a field never filled in
   (`null`), not one emptied on purpose.
2. Absolute URL / `data:` → as-is
3. **Seeded image still untouched** → the copy in the repository, so deploying
   stays a plain `git pull`
4. The media disk
5. A file under `public/`
6. The fallback

### Seeded images and `seeded_hash`

`Media::import('assets/images/team/x.jpg', 'site')` copies a repository file
onto the media disk as `images/site/team-x.jpg`, registers it in the asset
library, and records its hash in `cms_assets.seeded_hash`.

That column is what distinguishes **"still the theme's file"** from **"an
editor replaced it"**:

| `seeded_hash` | Meaning | Behaviour |
| --- | --- | --- |
| set | The seeder's own file | The repository copy is served; a deploy may update it |
| `null` | Someone uploaded over it | The disk file is served; deploys leave it alone |

`Asset::register()` clears the hash when the file at a path no longer matches
it, so **every** upload route is covered rather than one screen.

Without this, a deploy re-running `Media::import()` overwrote an editor's
upload with the original, and the page served the repository copy anyway — an
image replaced in the panel appeared to work and reverted days later.

### Uploads

`CmsFields::image($name, $directory)` is the upload field: drop a file, or
"Choose an image" to pick one already in the library. Every route registers the
file as an asset, so an image uploaded on one screen is reusable on the next.
The field stores a plain path.

### Cropping

`PhynixMedia\Cms\Media\ImageProcessor::store($source, $preset, $directory)` crops to a
named preset from `cms.images.presets` and writes the configured format. Needs
the configured driver (`gd` or `imagick`) and write access to the disk.

---

## 14. Previews

Routes live in the package under `cms.preview.path` and authenticate against
the panel's guard directly (previews sit outside the panel):

| Route | Shows |
| --- | --- |
| `{path}/pages/{page}` | A saved page, published or not |
| `{path}/layouts/{layout}` | A layout |
| `{path}/components/{component}` | One library component |
| `{path}/{token}` | **Unsaved** changes, from `PreviewStore` |

`PreviewStore` keeps unsaved builder state for `cms.preview.ttl` minutes. An
expired token returns a 404 telling the editor to press Preview again.

In a preview, `RenderContext::$preview` is true: failed components explain
themselves in place, hidden items show a marker, and `cms::preview.bar` is
pushed onto `cms_body_end`.

---

## 15. Publishing to Blade files

For a change the components cannot express. `cms:publish-page` writes a page
out as a real Blade file; while the file exists, it draws the page.

```bash
php artisan cms:publish-page services/festival-cover
php artisan cms:publish-page --all --compact
php artisan cms:publish-page home --revert          # back to the panel
php artisan cms:publish-page home --use-file        # use the file again
php artisan cms:publish-page home --sync            # file data → panel
```

| Option | Effect |
| --- | --- |
| `--all` | Everything the action applies to |
| `--compact` | One line per section rather than copying each section's markup |
| `--force` | Replace a published or reverted file with a fresh publish |
| `--revert` | Back to the panel's content; the file is kept as `*.reverted.blade.php` |
| `--delete` | With `--revert`, delete the file instead |
| `--use-file` | Use the file again, with the edits made to it |
| `--sync` | Copy the data written in the file back into the panel; markup edits stay in the file |

`cms:publish-component` is the same for library components.

Published files land under `cms.publishing.views` / `.components`. The page's
`view` column points at the file; `publishedView()` returns it only while the
view actually exists, so deleting the file falls back to the panel content.

Published files use the directives in §18.

---

## 16. Seeding content

### A content definition

```php
use PhynixMedia\Cms\Content\ContentDefinition;
use PhynixMedia\Cms\Content\Blocks;

class SiteContent implements ContentDefinition
{
    public function components(): array
    {
        return ['site-cta' => ['name' => 'Call to action', 'type' => 'call-to-action', 'data' => [...]]];
    }

    public function layouts(): array
    {
        return ['default' => [
            'label' => 'Site layout',
            'is_default' => true,
            'header' => [Blocks::make('site-header')],
            'footer' => [Blocks::library('site-footer')],
        ]];
    }

    public function pages(): array
    {
        return ['about' => [
            'title' => 'About us',
            'layout' => 'default',
            'content' => [Blocks::make('heading', ['heading' => 'About us'])],
        ]];
    }
}
```

List it in `cms.content`. Everything is keyed by a stable handle (the URL for
pages), so running it again updates rather than duplicates.

`Blocks::make($type, $data)` is an inline component; `Blocks::library($handle)`
is a reference resolved to an id at install time.

### Install semantics

`ContentInstaller::install($definitions, force: false)`:

| Target | Without `--force` |
| --- | --- |
| Component whose handle exists | **Kept** |
| Layout with any region filled | **Kept** |
| Page that exists **and has content** | **Kept** |
| Anything missing | Created |

So the command is safe to re-run; `--force` makes the definitions win and asks
for confirmation in an interactive terminal.

### The seeder chain

`CmsDatabaseSeeder` calls, in order:

1. `WebPageLayoutSeeder` — layouts must exist before pages point at them
2. `WebPageTemplateSeeder` — pages
3. `MenuSeeder` — places pages in the menus, so pages must already exist
4. `CmsContentSeeder` — fills in what the layouts and pages are made of

Call it from the host's `DatabaseSeeder`.

### CmsSeedGuard

The first three are destructive — they reset page settings and menus to what
the code says. They stand aside when **all three** hold:

```php
$existingSite                                  // the site already has pages
 && app()->isProduction()                      // APP_ENV=production
 && ! config('cms.seeding.overwrite_live')     // CMS_SEED_OVERWRITE
```

**Note the second condition.** On a server whose `APP_ENV` is not
`production`, the guard does not protect, and a `db:seed` overwrites panel
edits. Set `APP_ENV=production` on live servers.

`CmsContentSeeder` is not guarded, because it keeps existing content anyway.

---

## 17. Console commands

| Command | Does |
| --- | --- |
| `php artisan auth:admin` | Create an administrator. Options `--firstname --lastname --email --password` for unattended setup; prompts otherwise. |
| `php artisan cms:seed-components` | Install `cms.content`. `--only=components\|layouts\|pages` (repeatable), `--force`. Checks the tables exist first. |
| `php artisan cms:publish-page {url}*` | Publish pages to Blade (§15) |
| `php artisan cms:publish-component {handle}*` | Publish library components to Blade |
| `php artisan cms:import-assets` | Add files already on the media disk to the asset library. `--path=`, `--group=`, `--dry-run`. |

Host-side commands in this project (not part of the package):
`cms:move-shared-settings`.

---

## 18. Blade helpers and directives

### Helpers

Loaded from `src/helpers.php`. Each is guarded with `function_exists`, so a
host that already defines one of these names keeps its own.

| Helper | Returns |
| --- | --- |
| `setting($key, $default = null)` | A shared setting; falls back to the config-declared default |
| `site_text($text)` | Text with `{phone}`-style tokens resolved |
| `menu()` | Header menu tree |
| `footer_menu()` | Footer columns |
| `category()` | Blog categories |
| `_blogs()` | Published posts |
| `cms_image($source, $fallback)` | URL for a legacy image value |
| `cms_media($value, $fallback)` | URL for a component image value (§13) |
| `cms_component($reference, $page)` | Renders a library component by handle or id |
| `cms_class($name)` | A class from `cms.rendering.classes` |
| `pagedata()`, `groups()`, `sort_keys()` | Legacy blocks system |

### Directives

| Directive | Use |
| --- | --- |
| `@cmsComponent('site-cta')` | Place a library component in any Blade view. Inside a CMS page it knows which page it is on. |
| `@cmsSection(...) … @endCmsSection` | Used by published pages; extracts the section's variables and unsets them afterwards so one section cannot leak into the next |
| `@cmsBlock('type', [...])` | Renders one component type inline; used by published files |

---

## 19. The read API

`PhynixMedia\Cms\Facades\Cms`, backed by the `Cms` singleton.

```php
Cms::page('about-us');            // ?Templates — by url
Cms::render($page);               // View — the full page
Cms::component($ref, $page);      // HtmlString — a library component
Cms::menu();                      // header menu tree
Cms::footerMenu();                // footer columns
Cms::setting('site.email');       // shared setting (declared default if unset)
Cms::putSettings(['site.email' => '…']);
Cms::blogs();                     // published posts
Cms::blogsPage($categoryId, 9);   // paginated
Cms::recentBlogs(2);
Cms::blog($id);
Cms::blogsInCategory($categoryId);
Cms::categories();
Cms::recordVisit($url);
```

Constants: `Cms::PUBLISHED` (`1`), `Cms::FOOTER_NONE` (`'none'`),
`Cms::FOOTER_COLUMNS_SETTING`. Statics: `Cms::footerColumnList()`,
`Cms::footerColumns()`.

**Never query the CMS models directly from a site's views.** Everything the
public site needs is here, and the API is what absorbs schema changes.

---

## 20. Blog, enquiries, visits, SEO

- **Blog** — `web_blogs`, `web_blogs_category`. Public queries filter to
  `status = 1`, newest first; `blogsPage()` paginates nine per page by default.
  Posts manage their own uploads under `images/blog`.
- **Enquiries** — `web_enquiries`, surfaced as a read-only-style Filament
  resource. The host owns the form and the controller that writes rows.
- **Visits** — `page_visits` holds one counter per page URL per day/month/year;
  `Cms::recordVisit($url)` increments it. Shown by the `VisitsChart` and
  `CmsOverview` widgets.
- **SEO keywords** — `web_page_seo_keywords`; a page points at a set through
  `seo_keyword_id`.

---

## 21. The admin panel

`PhynixMedia\Cms\Providers\CmsPanelProvider`, registered by the package.

Navigation groups, in order: **Content, Gallery, Blog, Insights, Settings**.

Package resources: Pages, Component library, Assets, Blocks, Layouts, SEO
Keyword Sets, Blog, Blog categories, Enquiries, Page visits, Administrators.
Package pages: Dashboard, Settings. Widgets: `CmsOverview`, `VisitsChart`.

Resources are listed explicitly rather than discovered from disk: the list
doubles as the package's table of contents, and a resource cannot appear in the
panel by accident.

The host adds its own screens through `cms.panel.resources`, `.pages`,
`.widgets`, and brands the panel through `cms.panel.logo`, `.primary`,
`.favicon`.

---

## 22. Integration checklist

For a new Laravel application:

1. **Copy the package** to `packages/cms`.
2. **composer.json** — add the path repository, require `ambulance/cms`, and
   add `packages/cms/src/helpers.php` to the host's `autoload.files`.
   `composer dump-autoload`.
3. **`php artisan migrate`** — creates every table in §6.
4. **`php artisan storage:link`**.
5. **`php artisan auth:admin`** — first administrator.
6. **Write a shell** (§8) and point `cms.rendering.shell` at it.
7. **Write component types** for the theme's sections and list them in
   `cms.components.types`.
8. **Add the page route** (§7), excluding the panel and preview paths.
9. **Declare shared settings** in `cms.settings.sections` if the site has any.
10. **Write a `ContentDefinition`** and list it in `cms.content`, so a fresh
    checkout can rebuild the site with `cms:seed-components`.
11. **Call `CmsDatabaseSeeder`** from the host's `DatabaseSeeder`.
12. **Set `APP_ENV=production` on live servers** so `CmsSeedGuard` protects
    panel edits.

---

## 23. Invariants and things that bite

- A page returns 404 when its own `status` is not `1`, whatever its layout
  says. The page is the public gate.
- `cms.panel.path` and `cms.preview.path` must be excluded from the host's
  catch-all route.
- A shell missing `cms_header`, `content` or `cms_footer` loses that region
  silently.
- An unpublished library component disappears everywhere it is referenced.
- Menus render three levels; the page form prevents deeper choices.
- A slug change moves a public page. Page title changes do not update slugs.
- A published page's builder content still exists but is not live until
  `--revert`. Its layout is still live.
- If a component type is removed or renamed while data still references it,
  live output omits that section and previews name it.
- `Setting::allSettings()` is cached forever. Write through the model, or call
  `Setting::flush()`.
- `CmsSeedGuard` only protects when `APP_ENV=production`.
- After adding classmap seeders, run `composer dump-autoload` before
  `db:seed`.
- Image uploads need the configured driver, a writable disk and the storage
  symlink.
- Deleting a published Blade file silently returns the page to panel content —
  which is the intended escape hatch, not a bug.

---

## 24. Source map

| Concern | Files |
| --- | --- |
| Bootstrap | `src/CmsServiceProvider.php`, `src/config/cms.php` |
| Read API | `src/Cms.php`, `src/Facades/Cms.php`, `src/helpers.php` |
| Rendering | `src/Rendering/{PageRenderer,ComponentRenderer,RenderContext}.php`, `src/resources/views/page.blade.php` |
| Shell | `src/resources/views/shell.blade.php` |
| Components | `src/Components/{ComponentType,ComponentRegistry}.php`, `src/Components/Types/` |
| Builder field | `src/Filament/Support/ComponentBuilder.php` |
| Form helpers | `src/Filament/Support/CmsFields.php` |
| Panel | `src/Providers/CmsPanelProvider.php`, `src/Filament/` |
| Settings | `src/Settings/{SettingsSchema,Tokens}.php`, `src/Filament/Pages/SiteSettings.php` |
| Media | `src/Support/Media.php`, `src/Media/ImageProcessor.php` |
| Previews | `src/Http/Controllers/PreviewController.php`, `src/Preview/PreviewStore.php`, `src/routes/web.php` |
| Publishing | `src/Publishing/`, `src/Console/Publish*Command.php` |
| Seeding | `src/Content/`, `src/database/seeds/` |
| Models | `src/Models/` |
| Migrations | `src/database/migrations/` |
