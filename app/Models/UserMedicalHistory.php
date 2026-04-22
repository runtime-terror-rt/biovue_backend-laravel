<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMedicalHistory extends Model
{
    protected $guarded = [];

    public function medicalHistory() 
    {
        return $this->hasOne(UserMedicalHistory::class, 'user_id');
    }
}
