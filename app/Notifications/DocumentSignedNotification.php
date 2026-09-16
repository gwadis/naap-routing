<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class DocumentSignedNotification extends Notification
{
    public $document;
    public $signerName;

    public function __construct(Document $document, $signerName = null)
    {
        $this->document = $document;
        $this->signerName = $signerName;
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
            'message' => "Document '{$this->document->title}' has been signed and received",
            'type' => 'document_signed',
            'signer' => $this->signerName,
            'read_at' => null,
        ];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("NAAP Document Routing System - Transmission Completed - {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your document has been successfully received and signed.")
            ->line("Document: {$this->document->title}")
            ->line("Signed by: {$this->signerName}")
            ->line("Signed at: " . now()->format('M j, Y H:i'))
            ->action('View Document Details', url(route('track.detail', $this->document->id)))
            ->line('This signature serves as proof of delivery.')
            ->line('Thank you!');
    }
}
