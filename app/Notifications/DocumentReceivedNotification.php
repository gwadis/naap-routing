<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class DocumentReceivedNotification extends Notification
{
    public $document;
    public $receiverName;
    public $officeName;

    public function __construct(Document $document, ?string $receiverName = '', ?string $officeName = '')
    {
        $this->document     = $document;
        $this->receiverName = $receiverName ?: 'Unknown Receiver';
        $this->officeName   = $officeName ?: ($document->currentOffice?->name ?? 'Unknown Office');
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
            'message'        => "📬 Document '{$this->document->title}' has been received and scanned by {$this->receiverName} at {$this->officeName}.",
            'type'           => 'document_received',
            'receiver'       => $this->receiverName,
            'office'         => $this->officeName,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Document Received – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("📬 Your document has been received.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Received By: {$this->receiverName}")
            ->line("Office: {$this->officeName}")
            ->line("Time: " . now()->format('M j, Y h:i A'))
            ->action('Track Document', url(route('track.detail', $this->document->id)))
            ->line('Thank you for using NAAP Routing System.');
    }
}
