<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'plan_type',
        'user_id',
        'billing_cycle',
        'price',
        'duration',
        'member_limit',
        'features',
        'status',
        'projection_limit',
        'stripe_price_id',
        'stripe_price_id_annual',
    ];

    protected $casts = [
        'features' => 'array',       // JSON column automatically array hisebe handle korbe
        'status'   => 'boolean',
        'price'    => 'decimal:2',   // decimal precision 2
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Plan creator (admin / owner)
    public function users()
    {
        return $this->hasMany(User::class, 'plan_id');
    }

    public function payments()
    {
        return $this->hasMany(PlanPayment::class, 'plan_id');
    }
    
    // Future subscription relation
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }



    // Accessor: annual price
    public function getAnnualPriceAttribute()
    {
        if ($this->price <= 0) {
            return 0; // free plan
        }

        return round($this->price * 12 * 0.9, 2); // 10% discount yearly
    }
}