<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealPlan extends Model
{
    use HasFactory;

    protected $table = 'meal_plans';

    protected $fillable = [
        'user_id',
        'target_calorie',
        'target_protein',
        'target_carbs',
        'target_fat',
    ];

    /**
     * A MealPlan belongs to a User
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * A MealPlan has many MealItems
     */
    public function items()
    {
        return $this->hasMany(MealItem::class, 'meal_plan_id');
    }
}