<?php

namespace Cms\Components\Types;

use Cms\Components\ComponentType;
use Cms\Rendering\RenderContext;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;

/**
 * Formatted text written in the panel's editor.
 */
class RichText extends ComponentType
{
    public function key(): string
    {
        return 'rich-text';
    }

    public function label(): string
    {
        return 'Rich text';
    }

    public function description(): ?string
    {
        return 'Headings, paragraphs, lists, links and quotes.';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function group(): string
    {
        return 'Text';
    }

    public function schema(): array
    {
        return [
            RichEditor::make('content')
                ->hiddenLabel()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'link'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList', 'blockquote'],
                    ['undo', 'redo'],
                ]),
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
        $text = is_string($data['content'] ?? null) ? trim(strip_tags($data['content'])) : '';

        return $text === '' ? null : str($text)->limit(60)->toString();
    }

    public function with(array $data, RenderContext $context): array
    {
        $content = $data['content'] ?? null;

        return [
            // Sanitised by Filament's renderer; it also accepts the editor's
            // JSON document, which is what a preview of unsaved changes holds.
            'html' => filled($content) ? RichContentRenderer::make($content)->toHtml() : '',
            'width' => $data['width'] ?? 'container',
        ];
    }
}
