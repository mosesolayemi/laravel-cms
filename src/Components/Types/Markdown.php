<?php

namespace Cms\Components\Types;

use Cms\Components\ComponentType;
use Cms\Rendering\RenderContext;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Illuminate\Support\Str;

/**
 * Text written in Markdown, for editors who prefer it to the rich editor.
 */
class Markdown extends ComponentType
{
    public function key(): string
    {
        return 'markdown';
    }

    public function label(): string
    {
        return 'Markdown';
    }

    public function icon(): string
    {
        return 'heroicon-o-hashtag';
    }

    public function group(): string
    {
        return 'Text';
    }

    public function schema(): array
    {
        return [
            MarkdownEditor::make('content')->hiddenLabel(),
            Select::make('width')
                ->options(Width::OPTIONS)
                ->default('container')
                ->selectablePlaceholder(false),
        ];
    }

    public function view(): string
    {
        return 'cms::components.rich-text';
    }

    public function summary(array $data): ?string
    {
        return filled($data['content'] ?? null) ? Str::limit(strip_tags((string) $data['content']), 60) : null;
    }

    public function with(array $data, RenderContext $context): array
    {
        return [
            'html' => Str::markdown((string) ($data['content'] ?? ''), ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'width' => $data['width'] ?? 'container',
        ];
    }
}
