<?php

namespace Cms\Providers;

use Cms\Filament\Resources\Admins\AdminResource;
use Cms\Filament\Resources\BlogCategories\BlogCategoryResource;
use Cms\Filament\Resources\Blocks\BlockResource;
use Cms\Filament\Resources\Blogs\BlogResource;
use Cms\Filament\Resources\Enquiries\EnquiryResource;
use Cms\Filament\Resources\Layouts\LayoutResource;
use Cms\Filament\Resources\PageVisits\PageVisitResource;
use Cms\Filament\Resources\Pages\PageResource;
use Cms\Filament\Resources\SeoKeywords\SeoKeywordResource;
use Cms\Filament\Widgets\CmsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The CMS back office.
 *
 * Resources are listed explicitly rather than discovered from disk: the list
 * doubles as the package's table of contents, and a resource cannot appear in
 * the panel by accident.
 */
class CmsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id(config('cms.panel.id'))
            ->path(config('cms.panel.path'))
            ->brandName(config('cms.panel.brand'))
            ->login()
            ->authGuard(config('cms.auth.guard'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                'Content',
                'Blog',
                'Insights',
                'Settings',
            ])
            ->resources([
                PageResource::class,
                BlockResource::class,
                LayoutResource::class,
                SeoKeywordResource::class,
                BlogResource::class,
                BlogCategoryResource::class,
                EnquiryResource::class,
                PageVisitResource::class,
                AdminResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                CmsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
