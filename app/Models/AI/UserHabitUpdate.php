<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UserHabitUpdate extends Model
{
    use HasFactory;

    protected $table = 'user_habit_updates';

    protected $fillable = [
        'user_id',
        'focus_on_trends',

        'sleep_status',
        'sleep_why_this_matters',
        'sleep_biovue_insights',

        'nutrition_status',
        'nutrition_why_this_matters',
        'nutrition_biovue_insights',

        'activity_status',
        'activity_why_this_matters',
        'activity_biovue_insights',

        'stress_status',
        'stress_why_this_matters',
        'stress_biovue_insights',

        'hydration_status',
        'hydration_why_this_matters',
        'hydration_biovue_insights',
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}