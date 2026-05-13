<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification
{
    use Queueable;

    public $title;
    public $reminder_content;
    public $type;

    public function __construct($title, $message, $type)
    {
        $this->title = $title;
        $this->reminder_content = $message;
        $this->type = $type;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->view('emails.sendreminder', [ 
                'notifiable' => $notifiable,
                'title' => $this->title,
                'body' => $this->reminder_content,
                'type' => $this->type,
            ]);
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->reminder_content,
            'type' => $this->type,
        ];
    }
}