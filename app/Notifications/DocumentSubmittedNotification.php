<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use App\Models\Document;

class DocumentSubmittedNotification extends Notification
{
    public $document;
    public $senderName;

    public function __construct(Document $document, $senderName = null)
    {
        $this->document = $document;
        $this->senderName = $senderName;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'document_id'    => $this->document->id,
            'document_title' => $this->document->title,
            'type'           => 'document_submitted',
            'message'        => "A new document \"{$this->document->title}\" has been submitted and assigned to you.",
            'sender'         => $this->senderName ?? 'System',
            'priority'       => $this->document->priority,
            'status'         => $this->document->status,
        ];
    }
}
