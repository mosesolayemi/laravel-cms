<?php

namespace Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Templates extends Model
{
    //
    protected $table = 'web_page_templates';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'title',
        'seo_keyword_id',
        'meta_title',
        'meta_description',
        'layout_id',
        'url',
        'parent',
        'category',
        'page_order',
        'header_position',
        'footer_position',
        'status',
    ];

    public function blocks(){
        return $this->hasMany('Cms\Models\Pages\Blocks', 'template_id', 'id');
    }

    public function layouts(){
        return $this->hasOne('Cms\Models\Pages\Layouts', 'id', 'layout_id');
    }

    
    // with the block method, query the groups table
    public function groups(){
        return $this->hasManyThrough('Cms\Models\Pages\Groups', 'Cms\Models\Pages\Blocks', 'template_id', 'block_id', 'id', 'id');
    }

    // meta keyword
    public function metakeywords(){
        return $this->hasOne('Cms\Models\Pages\SeoKeyword', 'id', 'seo_keyword_id');
    }

    protected static function booted()
    {
        static::creating(function ($seo) {
            $seo->uuid = Str::uuid()->toString();
        });
    }
}
