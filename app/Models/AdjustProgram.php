<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class AdjustProgram extends Model
{
    use Notifiable;

    protected $fillable = [
        'user_id', 'target_weight', 'weekly_workouts', 'sleep_target_range',
        'hydration_target', 'show_program_goals', 'show_personal_targets',
        'show_progress_graphs', 'show_ai_insights', 'primary_focus_area',
        'note', 'programs'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
