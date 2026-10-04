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
        $channels = ['database'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        if (method_exists($notifiable, 'isTelegramConnected') && $notifiable->isTelegramConnected() && app(\App\Services\TelegramService::class)->isConfigured()) {
            $channels[] = \App\Channels\TelegramChannel::class;
        }
        return $channels;
    }

    public function toTelegram($notifiable): string
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);
        $title = htmlspecialchars(\Illuminate\Support\Str::limit($this->document->title, 40));
        $signer = htmlspecialchars($this->signerName ?? 'an authorized signatory');
        $naapUrl = url('/track?tracking_number=' . $tracking);

        return "✍️ <b>NAAP DOCUMENT SIGNED</b>\n\n"
             . "Document <b>{$title}</b> has been signed by <b>{$signer}</b>.\n\n"
             . "<b>Tracking ID:</b> <code>{$tracking}</code>\n\n"
             . "👉 <a href=\"{$naapUrl}\">View Signed Document</a>";
    }

    public function toSms($notifiable): string
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);
        $title = \Illuminate\Support\Str::limit($this->document->title, 25);
        $signer = $this->signerName ?? 'an authorized signatory';
        return "NAAP UPDATE: Document [{$tracking}] '{$title}' was successfully signed by {$signer}.";
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
