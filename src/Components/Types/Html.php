<?php

namespace PhynixMedia\Cms\Components\Types;

use PhynixMedia\Cms\Components\ComponentType;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Raw HTML — an embed, a map, a widget someone else supplied.
 *
 * Output exactly as written. Panel access is limited to administrators, who
 * can already change anything on the site; this is for the markup no other
 * component covers.
 */
class Html extends ComponentType
{
    public function key(): string
    {
        return 'html';
    }

    public function label(): string
    {
        return 'HTML / embed code';
    }

    public function description(): ?string
    {
        return 'Paste embed code or markup; it is output exactly as written.';
    }

    public function icon(): string
    {
        return 'heroicon-o-code-bracket';
    }

    public function group(): string
    {
        return 'Advanced';
    }

    public function schema(): array
    {
        return [
            TextInput::make('label')
                ->label('Name')
                ->helperText('Only shown in the panel, to tell this block apart from others.'),
            CodeEditor::make('html')
                ->label('HTML')
                ->language(Language::Html)
                ->required(),
            Toggle::make('contained')
                ->label('Keep within the page width')
                ->default(true),
        ];
    }

    public function view(): string
    {
        return 'cms::components.html';
    }
}
