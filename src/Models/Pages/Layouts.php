<?php

namespace PhynixMedia\Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

/**
 * The frame a page is rendered in: a shell view plus builder regions around
 * the page's own content.
 */
class Layouts extends Model
{
    //
    protected $table = 'web_page_layouts';

    /**
     * The builder regions, in the order they render, with their panel labels.
     */
    public const REGIONS = [
        'header' => 'Header',
        'before_content' => 'Before content',
        'after_content' => 'After content',
        'footer' => 'Footer',
    ];

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'label',
        'layout',
        'shell',
        'header',
        'before_content',
        'after_content',
        'footer',
        'is_default',
        'status',
    ];

    protected $casts = [
        'header' => 'array',
        'before_content' => 'array',
        'after_content' => 'array',
        'footer' => 'array',
        'is_default' => 'boolean',
    ];

    public function templates(){
        return $this->hasMany('PhynixMedia\Cms\Models\Pages\Templates', 'layout_id', 'id');
    }

    /**
     * The layout new pages start with.
     */
    public static function defaultLayout(): ?self
    {
        return static::query()
            ->where('status', 1)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    protected static function booted()
    {
        static::creating(function ($layout) {
            $layout->uuid = Str::uuid()->toString();
            $layout->layout = filled($layout->layout) ? $layout->layout : Str::slug($layout->label);
        });

        // Only one layout can be the default.
        static::saved(function (Layouts $layout) {
            if ($layout->is_default && ($layout->wasChanged('is_default') || $layout->wasRecentlyCreated)) {
                static::query()->whereKeyNot($layout->getKey())->update(['is_default' => false]);
            }
        });
    }
}
