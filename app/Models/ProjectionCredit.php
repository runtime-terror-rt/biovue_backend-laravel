<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectionCredit extends Model
{
    protected $fillable = [
        'user_id',
        'member_limit',
        'projection_limit',
    ];

    // User relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
