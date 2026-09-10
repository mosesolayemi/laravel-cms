<?php

namespace Cms\Filament\Widgets;

use Cms\Models\Blogs\Blogs;
use Cms\Models\Pages\PageVisit;
use Cms\Models\Pages\Templates;
use Cms\Models\Pages\WebEnquiry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CmsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $visitsThisMonth = PageVisit::query()
            ->where('year', (int) date('Y'))
            ->where('month', (int) date('m'))
            ->sum('visits');

        return [
            Stat::make('Published pages', Templates::query()->where('status', 1)->count()),
            Stat::make('Blog posts', Blogs::query()->where('status', 1)->count()),
            Stat::make('Enquiries', WebEnquiry::query()->count()),
            Stat::make('Visits this month', number_format($visitsThisMonth)),
        ];
    }
}
