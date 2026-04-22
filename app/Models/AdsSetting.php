<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AdsSetting extends Model
{
    protected $fillable = [
        'ads_title', 'ads_type', 'image', 'placement', 'start_date', 'end_date'
    ];

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }
}
