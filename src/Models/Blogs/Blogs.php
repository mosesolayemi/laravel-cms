<?php

namespace Cms\Models\Blogs;

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

    public function category(){
        return $this->belongsTo('Cms\Models\Blogs\Category', 'category_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($block) {
            $block->uuid = Str::uuid()->toString();
        });
    }

}