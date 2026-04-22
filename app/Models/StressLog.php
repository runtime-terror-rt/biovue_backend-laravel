<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StressLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'log_date',
        'stress_level',
        'mood',
        'description',
    ];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
    ];

    // User-er sathe relationship
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}