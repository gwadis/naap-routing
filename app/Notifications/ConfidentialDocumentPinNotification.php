<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class ConfidentialDocumentPinNotification extends Notification
{
    public $document;
    public $senderName;
    public $pin;

    public function __construct(Document $document, $senderName, $pin)
    {
        $this->document = $document;
        $this->senderName = $senderName;
        $this->pin = $pin;
    }

    public function via($notifiable)
    {
        return [\App\Channels\BrevoMailChannel::class];
    }

    public function toBrevoMail($notifiable)
    {
        $trackingNumber = $this->document->tracking_number ?? $this->document->qr_id;
        $originOffice = $this->document->originOffice?->name ?? 'N/A';
        $destinationOffice = $this->document->destinationOffice?->name ?? 'N/A';

        $subject = 'Confidential Document Access PIN';

        $body = "Hello {$notifiable->name},\n\n"
              . "You have received a confidential document.\n\n"
              . "Document: {$this->document->title}\n"
              . "Tracking Number: {$trackingNumber}\n"
              . "Sender Name: {$this->senderName}\n"
              . "Origin Office: {$originOffice}\n"
              . "Destination Office: {$destinationOffice}\n\n"
              . "Your Access PIN:\n"
              . "{$this->pin}\n\n"
              . "Use this PIN together with the document QR code (or the Unlock Document page) to securely access the document.\n\n"
              . "Please do not share this PIN with anyone.\n\n"
              . "Regards,\n"
              . "- NAAP Routing System";

        \Illuminate\Support\Facades\Log::channel('email')->info("Confidential PIN Email notification sent to {$notifiable->email}");

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
