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
        $channels = [\App\Channels\BrevoMailChannel::class];
        if (method_exists($notifiable, 'isTelegramConnected') && $notifiable->isTelegramConnected() && app(\App\Services\TelegramService::class)->isConfigured()) {
            $channels[] = \App\Channels\TelegramChannel::class;
        }
        return $channels;
    }

    public function toTelegram($notifiable): string
    {
        $trackingNumber = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);
        $naapUrl = url('/track?tracking_number=' . $trackingNumber);

        return "🔒 <b>NAAP SECURITY NOTIFICATION</b>\n\n"
             . "A <b>confidential document</b> requires your attention.\n\n"
             . "<b>Tracking ID:</b> <code>{$trackingNumber}</code>\n"
             . "<b>Sender:</b> " . htmlspecialchars($this->senderName ?? 'Authorized Office') . "\n\n"
             . "<i>Confidential content and access PIN are not sent via Telegram. Please open NAAP and verify via your registered Email OTP.</i>\n\n"
             . "👉 <a href=\"{$naapUrl}\">Open NAAP Secure Portal</a>";
    }

    public function toSms($notifiable): string
    {
        $trackingNumber = $this->document->tracking_number ?? $this->document->qr_id;
        $title = \Illuminate\Support\Str::limit($this->document->title, 25);
        return "NAAP SECURITY: Your access PIN code for confidential document [{$trackingNumber}] '{$title}' is: {$this->pin}. Do NOT share this PIN.";
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
              . "<b>{$this->pin}</b>\n\n"
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
