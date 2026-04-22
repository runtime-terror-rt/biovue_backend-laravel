<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealItem extends Model
{
    use HasFactory;

    protected $table = 'meal_items';

    protected $fillable = [
        'meal_plan_id',
        'meal_type',
        'food',
        'quantity',
        'unit',
    ];

    /**
     * A MealItem belongs to a MealPlan
     */
    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class, 'meal_plan_id');
    }
}