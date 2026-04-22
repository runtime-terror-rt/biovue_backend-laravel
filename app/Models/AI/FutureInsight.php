<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;

class FutureInsight extends Model
{
    protected $fillable = [
        'user_id',
        'priority',
        'category',
        'insight',
        'timeline',
        'expected_changes',
    ];

    protected $casts = [
        'expected_changes' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}