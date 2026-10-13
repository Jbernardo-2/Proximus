<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationalNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{type: string, title: string, message: string, severity: string, url: string}  $data
     */
    public function __construct(private readonly array $data) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, title: string, message: string, severity: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }
}
