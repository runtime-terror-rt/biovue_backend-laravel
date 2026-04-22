<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyPolicy extends Model
{
    protected $fillable = [
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'content' => 'array',   
        'is_active' => 'boolean',
    ];
    
}
