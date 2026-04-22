<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectionFutureGoal extends Model
{
    use HasFactory;

    /**
     * Table associated with the model.
     */
    protected $table = 'projection_future_goals';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'image',
        'duration',
        'resolution',
        'tier',
        'use_default_goal',
        'goal',
        'goal_description',
        'projection_id',
        'projection_url',
        'route',
        'timeframe',
        'est_bmi',
        'est_weight',
        'expected_changes',
        'confidence_score',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'expected_changes' => 'array',
        'use_default_goal' => 'boolean',
    ];

    /**
     * Relationship: Projection belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}