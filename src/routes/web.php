<?php

use PhynixMedia\Cms\Http\Controllers\PageController;
use PhynixMedia\Cms\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CMS preview routes
|--------------------------------------------------------------------------
|
| Administrators only; the controller checks the panel's guard. Served from
| `cms.preview.path` (default /cms-preview), which a host's catch-all page
| route must leave alone.
|
*/

Route::middleware(config('cms.preview.middleware', ['web']))
    ->prefix(config('cms.preview.path', 'cms-preview'))
    ->name('cms.preview.')
    ->group(function () {
        Route::get('pages/{page}', [PreviewController::class, 'page'])->name('page');
        Route::get('layouts/{layout}', [PreviewController::class, 'layout'])->name('layout');
        Route::get('components/{component}', [PreviewController::class, 'component'])->name('component');
        Route::get('{token}', [PreviewController::class, 'show'])->name('show');
    });

/*
|--------------------------------------------------------------------------
| CMS pages
|--------------------------------------------------------------------------
|
| A fallback, so a fresh installation serves its pages without the host
| adding anything to routes/web.php. Laravel matches it only after every
| real route has failed, so a host that defines its own page route keeps it
| — and the panel and preview paths, being real routes, are never reached
| by this. Set cms.routing.pages to false to turn it off entirely.
|
*/

if (config('cms.routing.pages', true)) {
    Route::fallback(PageController::class)
        ->middleware(config('cms.routing.middleware', ['web']))
        ->name('cms.page');
}
