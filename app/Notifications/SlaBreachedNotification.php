<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;
use App\Models\DocumentRouting;

class SlaBreachedNotification extends Notification
{
    public $document;
    public $routing;
    public $officeName;

    public function __construct(Document $document, DocumentRouting $routing)
    {
        $this->document  = $document;
        $this->routing   = $routing;
        $this->officeName = $routing->toOffice?->name ?? 'Unknown Office';
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'document_id'    => $this->document->id,
            'document_title' => $this->document->title,
            'tracking_number'=> $this->document->tracking_number ?? $this->document->qr_id,
            'message'        => "⚠️ SLA Breached: Document '{$this->document->title}' has exceeded its processing deadline at {$this->officeName}.",
            'type'           => 'sla_breached',
            'office'         => $this->officeName,
            'sla_due_at'     => $this->routing->sla_due_at?->toIso8601String(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("SLA BREACH – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("⚠️ An SLA deadline has been breached.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Current Office: {$this->officeName}")
            ->line("SLA Deadline: " . $this->routing->sla_due_at?->format('M j, Y h:i A'))
            ->action('View Document', url(route('track.detail', $this->document->id)))
            ->line('Please process this document immediately.');
    }
}
