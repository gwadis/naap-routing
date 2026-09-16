<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class VpaaAlarmNotification extends Notification
{
    public $document;
    public $currentOffice;
    public $hours;

    public function __construct(Document $document, $currentOffice, $hours)
    {
        $this->document = $document;
        $this->currentOffice = $currentOffice;
        $this->hours = $hours;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'message' => "High priority document '{$this->document->title}' has been idle at {$this->currentOffice} for {$this->hours} hours",
            'type' => 'vpaa_alarm',
            'read_at' => null,
        ];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->error()
            ->subject("NAAP Document Routing System - SLA Warning Alert: High Priority Document Stuck")
            ->greeting("Hello VPAA,")
            ->line("This is an automated system alarm from the NAAP Routing System.")
            ->line("A high priority document is currently idle at an office and requires attention.")
            ->line("Document Title: {$this->document->title}")
            ->line("Current Location: {$this->currentOffice}")
            ->line("Time Idle: {$this->hours} hours")
            ->action('View Tracking Detail', url(route('track.detail', $this->document->id)))
            ->line('Please take action to ensure the routing flow is completed.');
    }
}
