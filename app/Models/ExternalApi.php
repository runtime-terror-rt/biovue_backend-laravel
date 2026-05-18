<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalApi extends Model
{
    protected $table = 'external_apis';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'api_key',
        'projection_limit',
        'insite_limit',
        'start_date',
        'end_date',
    ];

    
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'projection_limit' => 'integer',
        'insite_limit' => 'integer',
    ];

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    
    public function scopeActive($query)
    {
        return $query->where('end_date', '>=', now())
                     ->where('start_date', '<=', now());
    }

    
    public function isExpired()
    {
        return $this->end_date ? $this->end_date->isPast() : true;
    }
}