<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionLog extends Model
{
    protected $fillable = [
        'user_id',
        'log_date',
        'meal_balance',
        'protein_servings',
        'vegetable_servings',
        'carb_quality',
        'fat_sources',
    ];

    // Ensure date is treated as a Carbon instance
    protected $casts = [
        'log_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}