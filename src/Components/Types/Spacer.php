<?php

namespace PhynixMedia\Cms\Components\Types;

use PhynixMedia\Cms\Components\ComponentType;
use Filament\Forms\Components\Select;

/**
 * Vertical breathing room between two sections.
 */
class Spacer extends ComponentType
{
    public const SIZES = [
        'sm' => 'Small',
        'md' => 'Medium',
        'lg' => 'Large',
        'xl' => 'Extra large',
    ];

    public function key(): string
    {
        return 'spacer';
    }

    public function label(): string
    {
        return 'Spacer';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrows-up-down';
    }

    public function group(): string
    {
        return 'Layout';
    }

    public function schema(): array
    {
        return [
            Select::make('size')
                ->options(self::SIZES)
                ->default('md')
                ->selectablePlaceholder(false),
        ];
    }

    public function view(): string
    {
        return 'cms::components.spacer';
    }

    public function summary(array $data): ?string
    {
        return self::SIZES[$data['size'] ?? 'md'] ?? null;
    }
}
