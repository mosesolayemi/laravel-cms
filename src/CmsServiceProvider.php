<?php

namespace Cms;

use Cms\Auth\Console\MakeAdminCommand;
use Cms\Components\ComponentRegistry;
use Cms\Components\Types;
use Cms\Console\ImportAssetsCommand;
use Cms\Console\PublishComponentCommand;
use Cms\Console\PublishPageCommand;
use Cms\Console\SeedComponentsCommand;
use Cms\Publishing\Sections;
use Cms\Providers\CmsPanelProvider;
use Cms\Rendering\ComponentRenderer;
use Cms\Rendering\PageRenderer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the CMS into a host application.
 *
 * The package is self-contained: it brings its own admin guard, its own
 * Filament panel, its own migrations and its own read API. A host app needs
 * only to register this provider.
 */
class CmsServiceProvider extends ServiceProvider
{
    /**
     * The generic component types the package ships. Nothing here may depend
     * on a particular site's markup or CSS.
     *
     * @var array<int, class-string<Components\ComponentType>>
     */
    public const CORE_COMPONENTS = [
        Types\RichText::class,
        Types\Markdown::class,
        Types\Heading::class,
        Types\Image::class,
        Types\Buttons::class,
        Types\Html::class,
        Types\Spacer::class,
        Types\BladeView::class,
    ];

    public function register(): void
    {
        $this->mergeConfigDeeply(__DIR__ . '/config/cms.php', 'cms');

        $this->app->singleton(Cms::class);
        $this->app->singleton(ComponentRenderer::class);
        $this->app->singleton(PageRenderer::class);

        // Core types first, so a host type registered under the same key
        // replaces the package's version.
        $this->app->singleton(ComponentRegistry::class, fn (): ComponentRegistry => (new ComponentRegistry)
            ->register(...(config('cms.components.core', true) ? self::CORE_COMPONENTS : []))
            ->register(...config('cms.components.types', [])));

        // The panel is part of the package, not the host app, so the package
        // registers it rather than relying on the app's provider list.
        $this->app->register(CmsPanelProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'cms');
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');

        $this->registerBladeDirectives();

        $this->registerAuthGuard();

        $this->publishes([
            __DIR__ . '/config/cms.php' => config_path('cms.php'),
        ], 'cms-config');

        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/cms'),
        ], 'cms-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAdminCommand::class,
                SeedComponentsCommand::class,
                PublishPageCommand::class,
                PublishComponentCommand::class,
                ImportAssetsCommand::class,
            ]);
        }
    }

    protected function registerBladeDirectives(): void
    {
        // Place a library component in any Blade view: @cmsComponent('site-cta').
        // Inside a CMS page the component knows which page it is on.
        Blade::directive('cmsComponent', fn (string $expression): string => "<?php echo cms_component({$expression}, "
            . '(($page ?? null) instanceof \\Cms\\Models\\Pages\\Templates ? $page : null)); ?>');

        // Used by pages published with cms:publish-page.
        foreach (Sections::directives() as $name => $compiler) {
            Blade::directive($name, $compiler);
        }
    }

    /**
     * Merge the package's config beneath the host's, at any depth.
     *
     * Laravel's own mergeConfigFrom only merges the top level, so a host that
     * sets a single nested key — `panel.resources`, say — would silently drop
     * every sibling default the package ships alongside it.
     */
    protected function mergeConfigDeeply(string $path, string $key): void
    {
        $config = $this->app->make('config');

        $config->set($key, $this->deepMerge(
            require $path,
            $config->get($key, []),
        ));
    }

    /**
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $overrides
     * @return array<mixed>
     */
    protected function deepMerge(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $defaults[$key] = (is_array($value) && is_array($defaults[$key] ?? null) && ! array_is_list($value))
                ? $this->deepMerge($defaults[$key], $value)
                : $value;
        }

        return $defaults;
    }

    /**
     * Register the admin guard from within the package.
     *
     * Keeping it here rather than in the host's config/auth.php means the CMS
     * cannot be installed in a half-configured state, and the host's own
     * guards are left untouched.
     */
    protected function registerAuthGuard(): void
    {
        $guard = config('cms.auth.guard');

        config([
            "auth.guards.{$guard}" => [
                'driver' => 'session',
                'provider' => $guard,
            ],
            "auth.providers.{$guard}" => [
                'driver' => 'eloquent',
                'model' => config('cms.auth.model'),
            ],
        ]);
    }
}
