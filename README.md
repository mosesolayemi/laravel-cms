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
  database/migrations/
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
