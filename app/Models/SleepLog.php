<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepLog extends Model
{
    protected $guarded = [];
    
    public function user() 
    {
        return $this->belongsTo(User::class);
    }

    public function getIsQualitySleepAttribute() 
    {
        return $this->sleep_hours >= 7;
    }
}
