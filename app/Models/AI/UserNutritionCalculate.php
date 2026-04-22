<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNutritionCalculate extends Model
{
    use HasFactory;

    protected $table = 'user_nutrition_calculates';

    protected $fillable = [
        'user_id',
        'log_date',
        'foods',
        'calories_value',
        'calories_unit',
        'protein_value',
        'protein_unit',
        'carbs_value',
        'carbs_unit',
        'fat_value',
        'fat_unit',
        'total',
    ];

    protected $casts = [
        'foods' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}