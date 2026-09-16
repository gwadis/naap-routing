<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    public $category;
    public $message;
    public $metadata;

    public function __construct($category, $message, array $metadata = [])
    {
        $this->category = $category;
        $this->message = $message;
        $this->metadata = $metadata;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => $this->category, // e.g. Security, Routing, Approval, Upload, System, User
            'message' => $this->message,
            'metadata' => $this->metadata,
            'read_at' => null
        ];
    }
}
