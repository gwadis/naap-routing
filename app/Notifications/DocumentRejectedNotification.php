<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class DocumentRejectedNotification extends Notification
{
    public $document;
    public $rejecterName;
    public $officeName;
    public $reason;

    public function __construct(Document $document, ?string $rejecterName = '', ?string $officeName = '', ?string $reason = '')
    {
        $this->document = $document;
        $this->rejecterName = $rejecterName ?: 'Receiver';
        $this->officeName = $officeName ?: 'Office';
        $this->reason = $reason ?? '';
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'tracking_number' => $this->document->tracking_number ?? $this->document->qr_id,
            'message' => "❌ Document '{$this->document->title}' has been rejected by {$this->rejecterName} at {$this->officeName}. Reason: {$this->reason}",
            'type' => 'document_rejected',
            'rejecter' => $this->rejecterName,
            'office' => $this->officeName,
            'reason' => $this->reason,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Document Rejected – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("❌ Your document has been rejected and routing has stopped.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Rejected By: {$this->rejecterName}")
            ->line("Office: {$this->officeName}")
            ->line("Reason: \"{$this->reason}\"")
            ->line("Time: " . now()->format('M j, Y h:i A'))
            ->action('Track Document', url(route('track.detail', $this->document->id)))
            ->line('Thank you for using NAAP Routing System.');
    }
}
