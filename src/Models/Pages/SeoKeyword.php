<?php

namespace Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class SeoKeyword extends Model
{
    //
    protected $table = 'web_page_seo_keywords';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'meta_title',
        'meta_description',
        'meta_keywords',
        'in_header',
        'in_footer',
        'status',
    ];

    protected static function booted()
    {
        static::creating(function ($seo) {
            $seo->uuid = Str::uuid()->toString();
        });
    }

    public function templates(){
        return $this->belongsTo('Cms\Models\Pages\Templates', 'id', 'seo_keyword_id');
    }

}
