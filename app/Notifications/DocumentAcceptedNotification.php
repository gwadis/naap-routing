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
        $receiver = htmlspecialchars($this->receiverName);
        $office = htmlspecialchars($this->officeName);
        $remarks = $this->remarks ? ("\n<b>Remarks:</b> " . htmlspecialchars($this->remarks)) : '';
        $naapUrl = url('/track?tracking_number=' . $tracking);

        return "✅ <b>NAAP DOCUMENT ACCEPTED</b>\n\n"
             . "Document <b>{$title}</b> was accepted by <b>{$receiver}</b> at <b>{$office}</b>.{$remarks}\n\n"
             . "<b>Tracking ID:</b> <code>{$tracking}</code>\n\n"
             . "👉 <a href=\"{$naapUrl}\">View Document Details</a>";
    }

    public function toSms($notifiable): string
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);
        $title = \Illuminate\Support\Str::limit($this->document->title, 25);
        $remarks = $this->remarks ? (' Remarks: ' . \Illuminate\Support\Str::limit($this->remarks, 40)) : '';
        return "NAAP UPDATE: Document [{$tracking}] '{$title}' was accepted by {$this->receiverName} at {$this->officeName}.{$remarks}";
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
