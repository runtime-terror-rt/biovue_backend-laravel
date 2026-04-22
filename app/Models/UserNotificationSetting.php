<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    protected $fillable = [
        'user_id', 'coach_messages', 'goal_updates', 'ai_insights',
        'missed_checkin_alerts', 'program_milestone_updates', 'weekly_summary_email',
        'auto_remind_missed_checkins', 'default_reminder_time',
        'check_in_reminder_alerts', 'subscription_updates'
    ];
}
