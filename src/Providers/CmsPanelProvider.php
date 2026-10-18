<?php

namespace PhynixMedia\Cms\Providers;

use PhynixMedia\Cms\Filament\Pages\SiteSettings;
use PhynixMedia\Cms\Filament\Resources\Admins\AdminResource;
use PhynixMedia\Cms\Filament\Resources\Assets\AssetResource;
use PhynixMedia\Cms\Filament\Resources\BlogCategories\BlogCategoryResource;
use PhynixMedia\Cms\Filament\Resources\Blocks\BlockResource;
use PhynixMedia\Cms\Filament\Resources\Blogs\BlogResource;
use PhynixMedia\Cms\Filament\Resources\Components\ComponentResource;
use PhynixMedia\Cms\Filament\Resources\Enquiries\EnquiryResource;
use PhynixMedia\Cms\Filament\Resources\Layouts\LayoutResource;
use PhynixMedia\Cms\Filament\Resources\PageVisits\PageVisitResource;
use PhynixMedia\Cms\Filament\Resources\Pages\PageResource;
use PhynixMedia\Cms\Filament\Resources\SeoKeywords\SeoKeywordResource;
use PhynixMedia\Cms\Filament\Widgets\CmsOverview;
use PhynixMedia\Cms\Filament\Widgets\VisitsChart;
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
            // A site that sets `panel.logo` gets its own mark in the sidebar
            // and on the login screen; the brand name stays as the alt text.
            ->brandLogo(fn () => ($logo = config('cms.panel.logo')) ? asset($logo) : null)
            ->darkModeBrandLogo(fn () => ($logo = config('cms.panel.logo_dark') ?: config('cms.panel.logo')) ? asset($logo) : null)
            ->brandLogoHeight(config('cms.panel.logo_height', '2.25rem'))
            ->favicon(fn () => ($icon = config('cms.panel.favicon')) ? asset($icon) : null)
            ->login()
            ->authGuard(config('cms.auth.guard'))
            ->colors([
                'primary' => ($hex = config('cms.panel.primary'))
                    ? Color::hex($hex)
                    : Color::Amber,
            ])
            ->navigationGroups([
                'Content',
                'Gallery',
                'Blog',
                'Insights',
                'Settings',
            ])
            ->resources([
                PageResource::class,
                ComponentResource::class,
                AssetResource::class,
                BlockResource::class,
                LayoutResource::class,
                SeoKeywordResource::class,
                BlogResource::class,
                BlogCategoryResource::class,
                EnquiryResource::class,
                PageVisitResource::class,
                AdminResource::class,
                // Screens for content that belongs to the host site rather
                // than to the CMS itself.
                ...config('cms.panel.resources', []),
            ])
            ->pages([
                Dashboard::class,
                // The values shared across the site. It hides itself when the
                // host declares none; see cms.settings.
                SiteSettings::class,
                ...config('cms.panel.pages', []),
            ])
            ->widgets([
                CmsOverview::class,
                VisitsChart::class,
                ...config('cms.panel.widgets', []),
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
