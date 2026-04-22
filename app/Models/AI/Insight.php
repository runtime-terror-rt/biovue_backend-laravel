<?php
namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Insight extends Model
{
    protected $fillable = [
        'user_id',
        'priority',
        'category',
        'insight',
        'why_this_matters',
        'expected_impact',
        'trainers_note',
        'action_steps'
    ];

    protected $casts = [
        'action_steps' => 'array'
    ];

    // User relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}