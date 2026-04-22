<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Partner extends Model
{
    protected $fillable = [
        'name',
        'email',
        'company',
        'image_url',
    ];

   
}
