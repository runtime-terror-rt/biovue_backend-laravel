<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientMessageNotification extends Notification
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
        $channels = ['database'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $targetUrl = match(true) {
            $notifiable->profession_type === 'supplement_supplier' => '/supplier-dashboard/messages',
            $notifiable->user_type === 'professional' => '/trainer-dashboard/messages',
            default => '/user-dashboard/messages',
        };

        return (new MailMessage)
            ->subject($this->title ?? 'New Message on BioVue')
            ->greeting("Hello {$notifiable->name},")
            ->line("You received a new message on BioVue:")
            ->line('"' . $this->message . '"')
            ->action('View & Respond to Message', url($targetUrl))
            ->line('Thank you for connecting with your clients on BioVue!')
            ->salutation('Best regards, The BioVue Team');
    }

    public function toDatabase($notifiable)
    {
        $targetUrl = match(true) {
            $notifiable->profession_type === 'supplement_supplier' => '/supplier-dashboard/messages',
            $notifiable->user_type === 'professional' => '/trainer-dashboard/messages',
            default => '/user-dashboard/messages',
        };

        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'url' => $targetUrl,
            'action_url' => $targetUrl,
            'link' => $targetUrl,
        ];
    }
}
