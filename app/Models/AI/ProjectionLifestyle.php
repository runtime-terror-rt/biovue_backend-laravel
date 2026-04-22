<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class ProjectionLifestyle extends Model
{
    use HasFactory;

    protected $table = 'projection_lifestyles';

    protected $fillable = [
        'user_id',
        'image',
        'duration',
        'resolution',
        'tier',
        'projection_id',
        'projection_url',
        'route',
        'timeframe',
        'est_bmi',
        'est_weight',
        'expected_changes',
        'confidence_score'
    ];

    protected $casts = [
        'expected_changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}