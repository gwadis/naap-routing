<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use App\Models\Document;
use App\Models\Office;

class DocumentForwardedNotification extends Notification
{
    public $document;
    public $senderName;
    public $toOfficeName;

    public function __construct(Document $document, $senderName = null, $toOfficeName = null)
    {
        $this->document = $document;
        $this->senderName = $senderName;
        $this->toOfficeName = $toOfficeName;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $office = $this->toOfficeName ?? ($this->document->currentOffice->name ?? 'your office');
        return [
            'document_id'    => $this->document->id,
            'document_title' => $this->document->title,
            'type'           => 'document_forwarded',
            'message'        => "Document \"{$this->document->title}\" has been forwarded to {$office} and assigned to you.",
            'sender'         => $this->senderName ?? 'System',
            'priority'       => $this->document->priority,
            'status'         => $this->document->status,
            'office'         => $office,
        ];
    }
}
