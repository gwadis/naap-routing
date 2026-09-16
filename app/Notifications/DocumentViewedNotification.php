<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;
use App\Models\User;

class DocumentViewedNotification extends Notification
{
    public $document;
    public $viewer;

    public function __construct(Document $document, User $viewer)
    {
        $this->document = $document;
        $this->viewer = $viewer;
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
            'message' => "👁️ Document '{$this->document->title}' has been viewed by {$this->viewer->name}.",
            'type' => 'document_viewed',
            'viewer_name' => $this->viewer->name,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Document Viewed – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("👁️ Your document has been opened and viewed.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Viewed By: {$this->viewer->name}")
            ->line("Time: " . now()->format('M j, Y h:i A'))
            ->action('Track Document', url(route('track.detail', $this->document->id)))
            ->line('Thank you for using NAAP Routing System.');
    }
}
