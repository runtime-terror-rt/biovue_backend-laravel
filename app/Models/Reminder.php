<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Reminder extends Model
{
    use Notifiable;

    protected $fillable = ['sender_id', 'client_id', 'reminder_type', 'message', 'in_app', 'push_notification'];
}
