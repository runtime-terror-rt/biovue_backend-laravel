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
        'user_id',
        'plan_id',
        'target_plan_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'stripe_session_id',
        'billing',
        'start_date',
        'end_date',
        'stripe_subscription_id',
        'is_trial',
        'trial_ends_at',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'status'        => 'string',
        'is_trial'      => 'boolean',
        'start_date'    => 'datetime',
        'end_date'      => 'datetime',
        'trial_ends_at' => 'datetime',
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

    public function targetPlan()
    {
        return $this->belongsTo(Plan::class, 'target_plan_id');
    }
}