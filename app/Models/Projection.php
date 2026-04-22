<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Projection extends Model
{
    protected $fillable = [
        'user_id', 'current_photo', 'time_horizon', 
        'projected_weight', 'projected_bmi', 
        'projected_photo', 'expected_changes', 'status_percentage'
    ];

    protected $casts = ['expected_changes' => 'array'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
