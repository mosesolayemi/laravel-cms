<?php

namespace PhynixMedia\Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Blocks extends Model
{
    //
    protected $table = 'web_page_blocks';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'template_id',
        'block_name',
        'multiple',
        'item_max',
        'status',
    ];

    public function templates(){
        return $this->belongsTo('PhynixMedia\Cms\Models\Pages\Templates', 'id', 'template_id');
    }

    public function groups(){
        return $this->hasMany('PhynixMedia\Cms\Models\Pages\Groups', 'block_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($block) {
            $block->uuid = Str::uuid()->toString();
        });
    }


}
