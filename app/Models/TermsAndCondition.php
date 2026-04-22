<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsAndCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',     // JSON text of terms
        'is_active',   // active or not
    ];

    protected $casts = [
        'content' => 'array',   // Automatically cast JSON to array
        'is_active' => 'boolean',
    ];
}
