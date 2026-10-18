<?php

namespace PhynixMedia\Cms\Filament\Widgets;

use PhynixMedia\Cms\Models\Pages\PageVisit;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Page visits over time, by day, week or month.
 *
 * page_visits holds one row per page per day, so every range here is a sum
 * over those rows. Rows written before the `day` column existed carry a
 * month's total with no day, so they count towards months but not towards
 * days or weeks — hence the null filter on the finer ranges.
 */
class VisitsChart extends ChartWidget
{
    protected ?string $heading = 'Visits';

    protected ?string $description = 'Page views across the site.';

    public ?string $filter = 'day';

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getFilters(): ?array
    {
        return [
            'day' => 'Last 30 days',
            'week' => 'Last 12 weeks',
            'month' => 'Last 12 months',
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$labels, $values] = match ($this->filter) {
            'week' => $this->byWeek(),
            'month' => $this->byMonth(),
            default => $this->byDay(),
        };

        return [
            'datasets' => [
                [
                    'label' => 'Visits',
                    'data' => $values,
                    // The site's own blue, so the panel reads as one product.
                    'backgroundColor' => '#2C51A3',
                    'hoverBackgroundColor' => '#33C1ED',
                    'borderRadius' => 4,
                    'maxBarThickness' => 28,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Totals keyed by date, for rows that carry a day.
     */
    protected function dailyTotals(CarbonImmutable $from): Collection
    {
        return PageVisit::query()
            ->whereNotNull('day')
            ->selectRaw('year, month, day, SUM(visits) as total')
            ->groupBy('year', 'month', 'day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                sprintf('%04d-%02d-%02d', $row->year, $row->month, $row->day) => (int) $row->total,
            ])
            ->filter(fn ($total, $date) => $date >= $from->toDateString());
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    protected function byDay(): array
    {
        $start = CarbonImmutable::today()->subDays(29);
        $totals = $this->dailyTotals($start);

        $labels = [];
        $values = [];

        for ($date = $start; $date <= CarbonImmutable::today(); $date = $date->addDay()) {
            // Only the 1st and every 5th day are labelled, so 30 ticks do not
            // collide; the tooltip carries the exact date.
            $labels[] = $date->day === 1 || $date->day % 5 === 0 ? $date->format('j M') : '';
            $values[] = $totals->get($date->toDateString(), 0);
        }

        return [$labels, $values];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    protected function byWeek(): array
    {
        $start = CarbonImmutable::today()->startOfWeek()->subWeeks(11);
        $totals = $this->dailyTotals($start);

        $labels = [];
        $values = [];

        for ($week = $start; $week <= CarbonImmutable::today(); $week = $week->addWeek()) {
            $end = $week->addDays(6);

            $labels[] = $week->format('j M');
            $values[] = $totals
                ->filter(fn ($total, $date) => $date >= $week->toDateString() && $date <= $end->toDateString())
                ->sum();
        }

        return [$labels, $values];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    protected function byMonth(): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(11);

        $totals = PageVisit::query()
            ->selectRaw('year, month, SUM(visits) as total')
            ->groupBy('year', 'month')
            ->get()
            ->mapWithKeys(fn ($row) => [sprintf('%04d-%02d', $row->year, $row->month) => (int) $row->total]);

        $labels = [];
        $values = [];

        for ($month = $start; $month <= CarbonImmutable::today(); $month = $month->addMonth()) {
            $labels[] = $month->format('M y');
            $values[] = $totals->get($month->format('Y-m'), 0);
        }

        return [$labels, $values];
    }

    protected function getOptions(): array
    {
        return [
            // One series, so the title names it and the legend is noise.
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(10, 34, 65, 0.06)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
