<?php

namespace Cms\Filament\Resources\Blogs\Schemas;

use Cms\Filament\Support\CmsFields;
use Cms\Models\Blogs\Blogs;
use Cms\Models\Blogs\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Writing a post is the job here, so the editor gets the full width of the
 * page and everything else is arranged around it rather than beside it.
 */
class BlogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // One column, so the editor is not squeezed into half the page.
            ->columns(1)
            ->components([
                Section::make('Post')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, string $operation): void {
                                // Only while drafting: renaming a published
                                // post must not move the URL people have.
                                if ($operation === 'create') {
                                    $set('url', self::uniqueSlug((string) $state));
                                }
                            }),

                        TextInput::make('url')
                            ->label('URL slug')
                            ->required()
                            // Written from the title, never typed: a hand
                            // entered slug is one more thing to get wrong, and
                            // the site looks posts up by id anyway.
                            ->disabled()
                            ->dehydrated()
                            ->prefix('/blogs/')
                            ->placeholder('From the title')
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'Created from the title as you type.'
                                : 'Fixed once published, so existing links keep working.'),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Uncategorised')
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(255),
                            ]),

                        CmsFields::published(),

                        Textarea::make('caption')
                            ->required()
                            ->rows(2)
                            ->maxLength(255)
                            ->helperText('The standfirst shown under the title in listings.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Cover image')
                    ->description('Shown at the top of the post and in listings.')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('cover')
                            ->hiddenLabel()
                            ->image()
                            ->disk(config('cms.media.disk', 'public'))
                            ->directory(config('cms.media.images', 'images') . '/blog')
                            ->imagePreviewHeight('160')
                            ->maxSize(10240),
                    ]),

                Section::make('Content')
                    ->schema([
                        RichEditor::make('content')
                            ->hiddenLabel()
                            ->required()
                            ->columnSpanFull()
                            // Room to actually write in, rather than a few
                            // lines that scroll after a paragraph.
                            ->extraInputAttributes(['style' => 'min-height: 30rem']),
                    ]),

                Section::make('SEO')
                    ->description('Optional. Falls back to the post title and caption.')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(255)
                            ->placeholder('Uses the post title')
                            ->columnSpanFull(),
                        Textarea::make('meta_description')
                            ->rows(3)
                            ->placeholder('Uses the caption'),
                        Textarea::make('meta_keywords')
                            ->rows(3)
                            ->helperText('Comma separated.'),
                    ]),
            ]);
    }

    /**
     * A URL friendly slug that no other post is already using.
     *
     * Posts are fetched by id, so a clash would not break a link — but two
     * posts sharing a slug makes the URLs indistinguishable to a reader.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 1;

        while (Blogs::query()
            ->where('url', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . ++$suffix;
        }

        return $slug;
    }
}
