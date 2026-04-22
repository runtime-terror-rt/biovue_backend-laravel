<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Schedule extends Model
{
    use Notifiable;
    
    protected $fillable = ['trainer_id', 'client_id', 'schedule_date', 'schedule_time', 'check_in_type', 'private_note', 'status'];
    
    public function client() {
        return $this->belongsTo(User::class, 'client_id');
    }
}
