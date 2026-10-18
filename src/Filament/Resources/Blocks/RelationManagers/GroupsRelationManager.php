<?php

namespace PhynixMedia\Cms\Filament\Resources\Blocks\RelationManagers;

use PhynixMedia\Cms\Filament\Support\CmsFields;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A group is one repetition of a block — a single card in a grid of cards.
 *
 * Its `element` column is a JSON array of {name, type, value} objects, which
 * the old API edited one element at a time through /api/cms/pages. The
 * repeater below edits the whole array in place instead.
 */
class GroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'groups';

    protected static ?string $title = 'Groups & content';

    /**
     * The element types the theme knows how to render.
     */
    public const TYPES = [
        'text' => 'Text',
        'html' => 'Rich text',
        'image' => 'Image',
        'video' => 'Video',
        'link' => 'Link',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The key the Blade view reads for this group.')
                    ->columnSpanFull(),

                CmsFields::published(),

                Repeater::make('element')
                    ->label('Elements')
                    ->default([])
                    ->addActionLabel('Add element')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Referenced in the view, e.g. "heading".'),

                        Select::make('type')
                            ->options(self::TYPES)
                            ->default('text')
                            ->required()
                            ->live(),

                        Textarea::make('value')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn (callable $get): bool => in_array($get('type'), ['text', 'link'], true)),

                        RichEditor::make('value')
                            ->columnSpanFull()
                            ->visible(fn (callable $get): bool => $get('type') === 'html'),

                        // Upload from the computer or reuse a file already in
                        // the library; either way the file is registered as an
                        // asset, so it can be picked again elsewhere.
                        CmsFields::image('value', 'blocks')
                            ->label('Image')
                            ->columnSpanFull()
                            ->visible(fn (callable $get): bool => $get('type') === 'image'),

                        FileUpload::make('value')
                            ->label('Video')
                            ->disk('public')
                            ->directory('videos')
                            ->acceptedFileTypes(['video/mp4', 'video/webm'])
                            ->columnSpanFull()
                            ->visible(fn (callable $get): bool => $get('type') === 'video'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('element')
                    ->label('Elements')
                    ->formatStateUsing(fn ($state): int => count((array) $state))
                    ->alignCenter(),
                IconColumn::make('status')->label('Published')->boolean(),
                TextColumn::make('updated_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }
}
