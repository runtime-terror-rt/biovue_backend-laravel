<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class PlanPayment extends Model
{
    use HasFactory, SoftDeletes, Notifiable;

    protected $fillable = [
    'user_id', 'plan_id', 'transaction_id', 'amount', 
    'currency', 'status', 'stripe_session_id', 'billing', 
    'start_date', 'end_date', 'stripe_session_id', 'stripe_subscription_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'string',

        // ✅ DATE casting (VERY IMPORTANT)
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}