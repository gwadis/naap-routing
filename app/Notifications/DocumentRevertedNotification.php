<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class DocumentRevertedNotification extends Notification
{
    public $document;
    public $reverterName;
    public $officeName;
    public $previousOfficeName;
    public $reason;

    public function __construct(Document $document, ?string $reverterName = '', ?string $officeName = '', ?string $previousOfficeName = '', ?string $reason = '')
    {
        $this->document = $document;
        $this->reverterName = $reverterName ?: 'Receiver';
        $this->officeName = $officeName ?: 'Office';
        $this->previousOfficeName = $previousOfficeName ?: 'Previous Office';
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
            'message' => "🔄 Document '{$this->document->title}' has been reverted back to {$this->previousOfficeName} by {$this->reverterName} from {$this->officeName}. Reason: {$this->reason}",
            'type' => 'document_reverted',
            'reverter' => $this->reverterName,
            'office' => $this->officeName,
            'previous_office' => $this->previousOfficeName,
            'reason' => $this->reason,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Document Reverted – {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("🔄 A document has been reverted back to your office for revision.")
            ->line("Document: {$this->document->title}")
            ->line("Tracking: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Reverted By: {$this->reverterName} from {$this->officeName}")
            ->line("Reverted To: {$this->previousOfficeName}")
            ->line("Reason: \"{$this->reason}\"")
            ->line("Time: " . now()->format('M j, Y h:i A'))
            ->action('Track Document', url(route('track.detail', $this->document->id)))
            ->line('Please review and take action accordingly.');
    }
}
