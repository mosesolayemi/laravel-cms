<?php

namespace Cms\Models\Pages;

use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    protected $table = 'page_visits';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'page_url',
        'visits',
        'year',
        'month',
    ];

}
