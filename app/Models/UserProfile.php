<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class UserProfile extends Model
{
    protected $guarded = [];

    protected $appends = ['unit_system'];

    public function getUnitSystemAttribute()
    {
        return $this->unit ?? 'imperial';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? (str_starts_with($value, 'http') ? $value : asset('storage/' . $value)) : null,
        );
    }

    protected function currentImage(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? (str_starts_with($value, 'http') ? $value : asset('storage/' . $value)) : null,
        );
    }

    protected $casts = [
        'specialties' => 'array',
        'services' => 'array',
    ];
}
