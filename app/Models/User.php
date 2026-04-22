<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Models\AI\Insight;
use App\Models\UserProfile;
use Laravel\Cashier\Billable;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasRoles, HasApiTokens, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'otp',
        'otp_expire_at',
        'terms_accepted',
        'status',
        'plan_id',
        'user_type',
        'profession_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function canAccessDataOf($targetUserId)
    {
        if ($this->id == $targetUserId) return true;

        return \DB::table('connect_user_proffesions')
            ->where('profession_id', $this->id)
            ->where('user_id', $targetUserId)
            ->exists();
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function hydrationLogs()
    {
        return $this->hasMany(HydrationLog::class);
    }

    public function sleepLogs()
    {
        return $this->hasMany(SleepLog::class);
    }

    public function stressLogs()
    {
        return $this->hasMany(StressLog::class);
    }

    public function nutritionLogs()
    {
        return $this->hasMany(NutritionLog::class);
    }

    public function targetGoals()
    {
        return $this->hasOne(TargetGoal::class);
    }

    public function adjustProgram()
    {
        return $this->hasOne(AdjustProgram::class, 'user_id');
    }

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }



    public function programSets()
    {
        return $this->belongsToMany(ProgramSet::class, 'connect_to_professions');
    }


    public function planPayments()
    {
        return $this->hasMany(PlanPayment::class, 'user_id');
    }

    //for auto updated user plan id
    public function plan()
    {
        return $this->belongsTo(\App\Models\Plan::class, 'plan_id');
    }


        public function insights()
    {
        return $this->hasMany(Insight::class);
    }

    public function sendUserNotify($type, $content)
    {
        $settings = $this->notificationSettings;
        $canSend = false;

        if (!$settings) return;

        if ($type === 'goal_updates') $canSend = $settings->enable_goal_updates;
        if ($type === 'coach_messages') $canSend = $settings->enable_coach_messages;
        if ($type === 'missed_checkin') $canSend = $settings->enable_missed_checkin_alerts;

        if ($canSend) {

            $this->notify(new \App\Notifications\GeneralNotification([
                'title' => $content['title'],
                'message' => $content['message'],
                'type' => $type,
                'url' => $content['url'] ?? '#'
            ]));
        }
    }

    public function notificationSettings()
    {
        return $this->hasOne(UserNotificationSetting::class,'user_id');
    }

    public function medicalHistory()
    {
        return $this->hasOne(UserMedicalHistory::class, 'user_id');
    }

    public function myProfessionals()
    {
        return $this->belongsToMany(User::class, 'connect_user_proffesions', 'user_id', 'profession_id')
                    ->withTimestamps();
    }

    public function myClients()
    {
        return $this->belongsToMany(User::class, 'connect_user_proffesions', 'profession_id', 'user_id')
                    ->withTimestamps();
    }

    public function connections()
    {
        return $this->myProfessionals();
    }

    public function projections()
    {
        return $this->hasMany(Projection::class, 'user_id');
    }

    public function projectionCredits() 
    {
        return $this->hasOne(ProjectionCredit::class, 'user_id');
    }

    protected $casts = [
        'user_type' => 'string',
        'status' => 'string',
    ];

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'id');
    }
}
