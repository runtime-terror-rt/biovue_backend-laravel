<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class AdjustProgram extends Model
{
    use Notifiable;

    protected $fillable = [
        'user_id', 'profession_id', 'target_weight', 'weekly_workouts', 'sleep_target_range',
        'hydration_target', 'show_program_goals', 'show_personal_targets',
        'show_progress_graphs', 'show_ai_insights', 'primary_focus_area',
        'note', 'programs', 'calories', 'protein', 'carbs', 'fat'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->profession_id)) {
                $model->profession_id = \Illuminate\Support\Facades\Auth::id()
                    ?: (\App\Models\TargetGoal::where('user_id', $model->user_id)->value('profession_id') ?: 1);
            }
        });
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
