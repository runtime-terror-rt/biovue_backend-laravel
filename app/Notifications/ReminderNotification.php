<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification implements ShouldQueue
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


    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->view('emails.remindersend', [ 
                'notifiable' => $notifiable,
                'title' => $this->title,
                'message' => $this->message,
                'type' => $this->type,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ];
    }

}
