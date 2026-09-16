<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;
use App\Models\DocumentRouting;

class SlaNearDueNotification extends Notification
{
    public $document;
    public $routing;
    public $officeName;
    public $remaining;

    public function __construct(Document $document, DocumentRouting $routing, string $remaining)
    {
        $this->document   = $document;
        $this->routing    = $routing;
        $this->officeName = $routing->toOffice?->name ?? 'Unknown Office';
        $this->remaining  = $remaining;
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
            'message'        => "⏰ SLA Near Due: Document '{$this->document->title}' deadline is approaching. Remaining: {$this->remaining}.",
            'type'           => 'sla_near_due',
            'office'         => $this->officeName,
            'remaining'      => $this->remaining,
            'sla_due_at'     => $this->routing->sla_due_at?->toIso8601String(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("SLA Deadline Approaching – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("⏰ An SLA deadline is approaching.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Current Office: {$this->officeName}")
            ->line("Deadline: " . $this->routing->sla_due_at?->format('M j, Y h:i A'))
            ->line("Time Remaining: {$this->remaining}")
            ->action('View Document', url(route('track.detail', $this->document->id)))
            ->line('Please process this document as soon as possible.');
    }
}
