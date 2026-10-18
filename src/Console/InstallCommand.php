<?php

namespace PhynixMedia\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;

/**
 * Takes a fresh Laravel application from `composer require` to a working CMS.
 *
 * Everything here is something a host would otherwise have to do by hand, and
 * every step is safe to run twice: an existing file is left alone unless
 * --force is given, and the seeders only fill in what is missing.
 */
class InstallCommand extends Command
{
    protected $signature = 'cms:install
        {--force : Overwrite the config and stub classes if they already exist}
        {--no-seed : Skip seeding the component library and page content}
        {--no-admin : Skip creating the first administrator}';

    protected $description = 'Install the CMS into this application';

    public function handle(): int
    {
        $this->components->info('Installing the CMS.');

        $this->publishConfig();
        $this->scaffoldSiteContent();

        if (! $this->migrate()) {
            return self::FAILURE;
        }

        $this->linkStorage();
        $this->seed();
        $this->createAdministrator();

        $this->newLine();
        $this->components->info(sprintf(
            'Done. The back office is at /%s.',
            ltrim((string) config('cms.panel.path', 'admin'), '/'),
        ));

        return self::SUCCESS;
    }

    /**
     * Write a slim config file rather than publishing the package's own.
     *
     * The package's config is long, and every value in it is a default that
     * the service provider already merges. Copying the whole thing into the
     * host would freeze those defaults at install time, so a later package
     * upgrade could not change any of them. The stub lists only what a site
     * normally overrides.
     */
    protected function publishConfig(): void
    {
        $this->copyStub('cms.php.stub', config_path('cms.php'), 'config/cms.php');
    }

    protected function scaffoldSiteContent(): void
    {
        $this->copyStub('SiteContent.php.stub', app_path('Cms/SiteContent.php'), 'app/Cms/SiteContent.php');

        File::ensureDirectoryExists(app_path('Cms/Components'));
    }

    protected function copyStub(string $stub, string $target, string $label): void
    {
        if (File::exists($target) && ! $this->option('force')) {
            $this->components->twoColumnDetail($label, '<fg=yellow>already exists</>');

            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy(__DIR__ . '/../../stubs/' . $stub, $target);

        $this->components->twoColumnDetail($label, '<fg=green>written</>');
    }

    protected function migrate(): bool
    {
        $this->newLine();

        $code = $this->call('migrate', $this->option('no-interaction') ? ['--force' => true] : []);

        if ($code !== self::SUCCESS) {
            $this->components->error('Migrations failed. Check the database connection in .env and run cms:install again.');

            return false;
        }

        return true;
    }

    /**
     * The media disk is served through public/storage, so without the symlink
     * every uploaded image 404s — with nothing in the logs to say why.
     */
    protected function linkStorage(): void
    {
        if (File::exists(public_path('storage'))) {
            $this->components->twoColumnDetail('public/storage', '<fg=yellow>already linked</>');

            return;
        }

        $this->callSilently('storage:link');
        $this->components->twoColumnDetail('public/storage', '<fg=green>linked</>');
    }

    protected function seed(): void
    {
        if ($this->option('no-seed')) {
            return;
        }

        $this->newLine();
        $this->call('cms:seed-components');
    }

    /**
     * There is no public sign-up for the panel by design, so an installation
     * without this step has no way in.
     */
    protected function createAdministrator(): void
    {
        if ($this->option('no-admin')) {
            return;
        }

        if (! Schema::hasTable('admins')) {
            return;
        }

        if ($this->option('no-interaction')) {
            $this->components->warn('No administrator created. Run: php artisan auth:admin');

            return;
        }

        $this->newLine();

        if (! confirm('Create the first administrator now?', default: true)) {
            $this->components->warn('Run `php artisan auth:admin` when you are ready.');

            return;
        }

        $this->call('auth:admin');
    }
}
