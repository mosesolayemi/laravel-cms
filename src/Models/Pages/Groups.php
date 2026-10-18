<?php

namespace PhynixMedia\Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Groups extends Model
{
    //
    protected $table = 'web_page_groups';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'block_id',
        'name',
        'element',
        'status',
    ];

    public function blocks(){
        return $this->belongsTo('PhynixMedia\Cms\Models\Pages\Blocks', 'id', 'block_id');
    }

    protected $casts = [
        'element' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($group) {
            $group->uuid = Str::uuid()->toString();
        });
    }

}
