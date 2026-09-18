<?php

namespace Cms\Components\Types;

use Cms\Components\ComponentType;
use Cms\Filament\Support\CmsFields;
use Cms\Rendering\RenderContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * A single image, optionally captioned and linked.
 */
class Image extends ComponentType
{
    public function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function group(): string
    {
        return 'Media';
    }

    public function schema(): array
    {
        return [
            CmsFields::image('image')->required(),
            TextInput::make('alt')
                ->label('Alternative text')
                ->helperText('Describes the image for screen readers and search engines.')
                ->maxLength(255),
            TextInput::make('caption')->maxLength(255),
            Grid::make(2)->schema([
                TextInput::make('link')->label('Link to')->placeholder('/contact or https://…'),
                Select::make('width')
                    ->options(Width::OPTIONS)
                    ->default('container')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    public function view(): string
    {
        return 'cms::components.image';
    }

    public function summary(array $data): ?string
    {
        return $data['caption'] ?? $data['alt'] ?? null;
    }

    public function with(array $data, RenderContext $context): array
    {
        return [
            ...$data,
            'src' => cms_media($data['image'] ?? null),
            'width' => $data['width'] ?? 'container',
        ];
    }
}
