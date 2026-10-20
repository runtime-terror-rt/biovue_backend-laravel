<?php

namespace App\Notifications;

use App\Traits\SendsFcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification
{
    use Queueable, SendsFcmPush;

    public $title;
    public $reminder_content;
    public $message;
    public $type;

    public function __construct($title, $message, $type)
    {
        $this->title = $title;
        $this->reminder_content = $message;
        $this->message = $message;
        $this->type = $type;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'fcm'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
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
            'url' => '/calendar',
            'action_url' => '/calendar',
            'link' => '/calendar',
        ];
    }
}