<?php

namespace PhynixMedia\Cms\Models\Pages;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class WebEnquiry extends Model
{
    //
    protected $table = 'web_enquiries';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
    ];

    // protected static function booted()
    // {
    //     static::creating(function ($enquiry) {
    //         $enquiry->uuid = Str::uuid()->toString();
    //     });
    // }
}
