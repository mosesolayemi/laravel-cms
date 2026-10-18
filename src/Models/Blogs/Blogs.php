<?php

namespace PhynixMedia\Cms\Models\Blogs;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Blogs extends Model
{
    //
    protected $table = 'web_blogs';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'category_id',
        'title',
        'caption',
        'cover',
        'url',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
    ];

    /**
     * The public URL for this post.
     *
     * The date and slug are decorative — posts are looked up by id — but they
     * make the link readable and shareable.
     */
    public function link(): string
    {
        return url(sprintf(
            '/blogs/%s/%s/%s',
            $this->getKey(),
            optional($this->created_at)->format('Y-m-d') ?: now()->format('Y-m-d'),
            $this->url ?: 'post',
        ));
    }

    public function coverUrl(): ?string
    {
        return $this->cover
            ? \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk', 'public'))->url($this->cover)
            : null;
    }

    public function category(){
        return $this->belongsTo('PhynixMedia\Cms\Models\Blogs\Category', 'category_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($block) {
            $block->uuid = Str::uuid()->toString();
        });
    }

}