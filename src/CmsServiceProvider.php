<?php

namespace Cms;

use Cms\Auth\Console\MakeAdminCommand;
use Cms\Providers\CmsPanelProvider;
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
    public function register(): void
    {
        $this->mergeConfigDeeply(__DIR__ . '/config/cms.php', 'cms');

        $this->app->singleton(Cms::class);

        // The panel is part of the package, not the host app, so the package
        // registers it rather than relying on the app's provider list.
        $this->app->register(CmsPanelProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->registerAuthGuard();

        $this->publishes([
            __DIR__ . '/config/cms.php' => config_path('cms.php'),
        ], 'cms-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAdminCommand::class,
            ]);
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
