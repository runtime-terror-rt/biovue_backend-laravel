<?php

namespace App\Notifications;
use App\Traits\SendsFcmPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ScheduleNotification extends Notification
{
    use Queueable, SendsFcmPush;

    public $schedule;
    public $status_type;
    public $title;
    public $message;
    public $type;

    public function __construct($schedule, $status_type)
    {
        $this->schedule = $schedule;
        $this->status_type = $status_type;
        $this->type = 'schedule_' . $status_type;
    }

    public function via($notifiable)
    {
        $channels = ['database', 'fcm'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function getTargetUrl($notifiable): string
    {
        return ($notifiable->user_type === 'professional') ? '/trainer-dashboard/calendar' : '/calendar';
    }

    public function toMail($notifiable)
    {
        $isTrainer = ($notifiable->user_type === 'professional');
        $otherName = $isTrainer
            ? ($this->schedule->client?->name ?? 'Client')
            : ($this->schedule->trainer?->name ?? 'Coach');

        $subject = $this->status_type === 'created' 
            ? "New Check-in Scheduled with {$otherName}" 
            : "Check-in Schedule Updated with {$otherName}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.schedule', [
                'notifiable'  => $notifiable,
                'schedule'    => $this->schedule,
                'status_type' => $this->status_type,
                'subject'     => $subject
            ]);
    }

    public function toArray($notifiable)
    {
        $isTrainer = ($notifiable->user_type === 'professional');
        $otherName = $isTrainer 
            ? ($this->schedule->client?->name ?? 'Client') 
            : ($this->schedule->trainer?->name ?? 'Coach');

        $this->title = $this->status_type === 'created' 
            ? 'New Check-in Scheduled' 
            : 'Schedule Updated';

        $this->message = $isTrainer
            ? "Check-in {$this->status_type} with {$otherName} on {$this->schedule->schedule_date} at {$this->schedule->schedule_time}."
            : "Check-in {$this->status_type} by {$otherName} for {$this->schedule->schedule_date} at {$this->schedule->schedule_time}.";

        $url = $this->getTargetUrl($notifiable);

        return [
            'title'       => $this->title,
            'type'        => $this->type,
            'schedule_id' => $this->schedule->id,
            'message'     => $this->message,
            'date'        => $this->schedule->schedule_date,
            'time'        => $this->schedule->schedule_time,
            'url'         => $url,
            'action_url'  => $url,
            'link'        => $url,
        ];
    }
}

