<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class TargetGoal extends Model
{
    use Notifiable;
    protected $fillable = [
        'user_id', 'target_weight', 'weekly_workout_goal', 
        'daily_step_goal', 'sleep_target', 'water_target', 'is_active', 
        'start_date', 'end_date', 'profession_id', 'supplement_recommendation'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'supplement_recommendation' => 'array',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
   
}
