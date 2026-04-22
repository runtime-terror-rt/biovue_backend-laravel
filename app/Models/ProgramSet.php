<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramSet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'programs_sets';

    protected $fillable = [
        'profession_id', // ✅ MUST ADD THIS
        'name',
        'duration',
        'primary_goal',
        'target_intensity',
        'description',
        'notes',
        'weekly_targets',
        'habit_focus_areas',
        'program_focus',
        'focus_areas',
        'habit_focus',
        'calories',
        'protein',
        'carbs',
        'fat',
        'supplement_recommendation',
        'supplement',
    ];

    protected $casts = [
        'habit_focus_areas' => 'array',
        'program_focus' => 'array',
        'focus_areas' => 'array',
        'habit_focus' => 'array',
        'supplement_recommendation' => 'array',
        'supplement' => 'array',
        'weekly_targets' => 'array',
    ];

    /**
     * Program belongs to a profession (trainer/user)
     */
    public function profession()
    {
        return $this->belongsTo(User::class, 'profession_id');
    }

    /**
     * Many users assigned to this program
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'connect_to_professions',
            'program_set_id',
            'user_id'
        );
    }
}