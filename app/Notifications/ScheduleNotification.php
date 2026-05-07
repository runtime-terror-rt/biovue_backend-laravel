<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ScheduleNotification extends Notification
{
    use Queueable;

    public $schedule;
    public $status_type;

    public function __construct($schedule, $status_type)
    {
        $this->schedule = $schedule;
        $this->status_type = $status_type;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $subject = $this->status_type == 'created' ? 'New Check-in Scheduled' : 'Schedule Updated';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your trainer has ' . $this->status_type . ' a check-in for you.')
            ->line('**Date:** ' . $this->schedule->schedule_date)
            ->line('**Time:** ' . $this->schedule->schedule_time)
            ->line('**Type:** ' . $this->schedule->check_in_type)
            ->action('View Dashboard', url('https://biovuedigitalwellness.com/user-dashboard'))
            ->line('Thank you for being with BioVue!');
    }

    public function toArray($notifiable)
    {
        return [
            'schedule_id' => $this->schedule->id,
            'message' => 'Schedule ' . $this->status_type,
            'date' => $this->schedule->schedule_date,
        ];
    }
}
