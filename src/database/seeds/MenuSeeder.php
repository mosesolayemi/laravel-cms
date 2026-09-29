<?php

use Cms\Models\Pages\Templates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Places every page in the header and footer menus.
 *
 * Reproduces exactly the menus that were hardcoded in menu.blade.php and
 * footer.blade.php before they became editable. Every page arrived flagged for
 * both menus, which would otherwise have dumped 26 links into each.
 *
 * Running it:
 *
 *     php artisan db:seed --class='\MenuSeeder'
 *
 * See GalleryCategorySeeder for why the leading backslash is needed.
 */
class MenuSeeder extends Seeder
{
    /**
     * Header: [url => [menu label or null, children in order]]
     */
    protected array $header = [
        'home' => ['Home', []],
        'about' => ['About Us', ['clinical-standards', 'team', 'medical-personnel']],
        'services' => [null, [
            'services/event-medical-cover',
            'services/festival-cover',
            'services/sporting-events',
            'services/motorsport',
            'services/water-sports',
            'services/fight-events',
        ]],
        'vehicles' => [null, [
            'vehicles/ambulances',
            'vehicles/6x6',
            'vehicles/rrvs',
            'vehicles/control-vehicles',
            'vehicles/quads',
            'vehicles/x-ray-unit',
        ]],
        'why-choose-us' => [null, []],
        'gallery' => [null, []],
        'blogs' => [null, []],
        'contact' => [null, []],
    ];

    /**
     * Footer: column => [url => footer wording, or null to use the page's own]
     */
    protected array $footer = [
        'services' => [
            'services/event-medical-cover' => null,
            'services/festival-cover' => null,
            'services/sporting-events' => null,
            'services/motorsport' => null,
            'services/water-sports' => null,
            'why-choose-us' => null,
            'services/fight-events' => null,
        ],
        'links' => [
            'about' => 'About Us',
            'services' => 'Our Services',
            'medical-personnel' => null,
            'gallery' => null,
            'testimonials' => null,
            'quote' => null,
            'faq' => 'Help & FAQS',
            'contact' => 'Contact Us',
        ],
    ];

    /**
     * Pages named here that are not in the database, collected so they can be
     * reported once at the end rather than failing one at a time.
     *
     * @var array<int, string>
     */
    protected array $missing = [];

    public function run()
    {
        // Would overwrite panel edits on a live site; see CmsSeedGuard.
        if (CmsSeedGuard::protects('MenuSeeder', $this->command)) {
            return;
        }

        if (! Schema::hasTable('web_page_templates')) {
            $this->say('error', 'No web_page_templates table. Run: php artisan migrate');

            return;
        }

        if (! Templates::query()->exists()) {
            $this->say('error', 'No pages to put in the menus. Run WebPageTemplateSeeder first, or just: php artisan db:seed');

            return;
        }

        // Start from a clean slate so a re-run cannot leave a page in a menu
        // it was removed from.
        Templates::query()->update([
            'header_position' => '0',
            'footer_position' => 'none',
            'menu_title' => null,
            'footer_title' => null,
            'footer_order' => 0,
            'parent' => 0,
        ]);

        $this->seedHeader();
        $this->seedFooter();
        $this->reportMissing();
    }

    /**
     * Name every page that could not be placed, and why that matters.
     *
     * Silently skipping produces a menu that is quietly missing links, which
     * is a great deal harder to work out than being told.
     */
    protected function reportMissing(): void
    {
        if (! $this->missing) {
            $this->say('info', 'Header and footer menus set.');

            return;
        }

        $this->say('warn', sprintf(
            '%d page(s) named in this seeder are not in the database, so they '
            . 'are missing from the menus: %s',
            count($this->missing),
            implode(', ', array_unique($this->missing)),
        ));

        $this->say('warn', 'Run WebPageTemplateSeeder first if those pages should exist, or remove them from MenuSeeder.');
    }

    /**
     * Write a line when running through Artisan; stay quiet otherwise.
     */
    protected function say(string $level, string $message): void
    {
        $this->command?->{$level}($message);
    }

    protected function seedHeader(): void
    {
        $order = 0;

        foreach ($this->header as $url => [$label, $children]) {
            $parent = $this->page($url);

            if (! $parent) {
                $this->missing[] = $url;

                continue;
            }

            $parent->update([
                'header_position' => '1',
                'menu_title' => $label,
                'parent' => 0,
                'page_order' => ++$order,
            ]);

            foreach ($children as $childUrl) {
                $child = $this->page($childUrl);

                if (! $child) {
                    $this->missing[] = $childUrl;

                    continue;
                }

                $child->update([
                    'header_position' => '1',
                    'parent' => $parent->id,
                    'page_order' => ++$order,
                ]);
            }
        }
    }

    protected function seedFooter(): void
    {
        foreach ($this->footer as $column => $links) {
            $order = 0;

            foreach ($links as $url => $label) {
                $page = $this->page($url);

                if (! $page) {
                    $this->missing[] = $url;

                    continue;
                }

                $page->update([
                    'footer_position' => $column,
                    // Only where the footer words it differently; otherwise it
                    // falls through to the menu label, then the page title.
                    'footer_title' => $label,
                    'footer_order' => ++$order,
                ]);
            }
        }
    }

    protected function page(string $url): ?Templates
    {
        return Templates::where('url', $url)->first();
    }
}
