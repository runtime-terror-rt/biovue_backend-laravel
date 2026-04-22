<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectionData extends Model
{
    protected $fillable = [
        'projection_id',
        'user_id',
        'input_image',
        'timeframe',
        'resolution',
        'projections_data',
        'summary_data'
    ];

    protected $casts = [
        'projections_data' => 'array', 
        'summary_data' => 'array',   
    ];

    // User relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
