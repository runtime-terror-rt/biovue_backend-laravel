<?php

namespace App\Notifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ScheduleNotification extends Notification implements ShouldQueue
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
            ->view('emails.schedule', [
                'notifiable' => $notifiable,
                'schedule' => $this->schedule,
                'status_type' => $this->status_type,
                'subject' => $subject
            ]);
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

