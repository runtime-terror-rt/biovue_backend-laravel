<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InsightNotification extends Notification
{
    use Queueable;

    public $title;
    public $message;
    public $type;
    /**
     * Create a new notification instance.
     */
    public function __construct($title, $message, $type)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
    }


    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject($this->title)
                    ->view('emails.reminder', [
                        'title' => $this->title,
                        'bodyMessage' => $this->message,
                    ]);
    }

    // public function toMail($notifiable)
    // {
    //     return (new MailMessage)
    //         ->subject($this->title)
    //         ->greeting('Hello!')
    //         ->line($this->message)
    //         ->action('Check My Plan', url('https://biovuedigitalwellness.com/pricing')) 
    //         ->line('Thank you for being with BioVue!');
    // }
}
