<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HydrationLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
    ];
}
