<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class DocumentAcceptedNotification extends Notification
{
    public $document;
    public $receiverName;
    public $officeName;
    public $remarks;

    public function __construct(Document $document, ?string $receiverName = '', ?string $officeName = '', ?string $remarks = '')
    {
        $this->document = $document;
        $this->receiverName = $receiverName ?: 'Receiver';
        $this->officeName = $officeName ?: 'Office';
        $this->remarks = $remarks ?? '';
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
            'message' => "✅ Document '{$this->document->title}' has been accepted by {$this->receiverName} at {$this->officeName}.",
            'type' => 'document_accepted',
            'receiver' => $this->receiverName,
            'office' => $this->officeName,
            'remarks' => $this->remarks,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Document Accepted – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("✅ Your document has been accepted.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Accepted By: {$this->receiverName}")
            ->line("Office: {$this->officeName}")
            ->line("Time: " . now()->format('M j, Y h:i A'));

        if ($this->remarks) {
            $mail->line("Remarks: \"{$this->remarks}\"");
        }

        return $mail
            ->action('Track Document', url(route('track.detail', $this->document->id)))
            ->line('Thank you for using NAAP Routing System.');
    }
}
