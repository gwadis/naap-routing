<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;

class DocumentRoutedNotification extends Notification
{
    public $document;
    public $sender;
    public $recipientType;
    public $otp;

    public function __construct(Document $document, $sender = null, $recipientType = 'receiver', $otp = null)
    {
        $this->document = $document;
        $this->sender = $sender;
        $this->recipientType = $recipientType;
        $this->otp = $otp;
    }

    public function via($notifiable)
    {
        $channels = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            $channels[] = 'database';
        }
        if (!empty($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
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
        $naapUrl = url('/track?tracking_number=' . $tracking);

        if ($this->recipientType === 'uploader') {
            return "📄 <b>NAAP DOCUMENT NOTIFICATION</b>\n\n"
                 . "Your document has been uploaded and is now routing.\n\n"
                 . "<b>Document:</b> {$title}\n"
                 . "<b>Tracking ID:</b> <code>{$tracking}</code>\n\n"
                 . "👉 <a href=\"{$naapUrl}\">Track Document</a>";
        }

        $sender = htmlspecialchars($this->sender ?? 'System');
        return "📄 <b>NAAP DOCUMENT NOTIFICATION</b>\n\n"
             . "A document has been routed to you by <b>{$sender}</b>.\n\n"
             . "<b>Document:</b> {$title}\n"
             . "<b>Tracking ID:</b> <code>{$tracking}</code>\n"
             . "<b>Priority:</b> {$this->document->priority}\n\n"
             . "👉 <a href=\"{$naapUrl}\">Open NAAP to Review</a>";
    }

    public function toSms($notifiable): string
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);
        $title = \Illuminate\Support\Str::limit($this->document->title, 25);
        if ($this->recipientType === 'uploader') {
            return "NAAP ALERT: Your document [{$tracking}] '{$title}' is in transit.";
        }
        $sender = $this->sender ?? 'another office';
        return "NAAP NOTICE: Document [{$tracking}] '{$title}' has been routed to you by {$sender}.";
    }

    public function toDatabase($notifiable)
    {
        $message = "Document '{$this->document->title}' has been routed to you";
        if ($this->recipientType === 'uploader') {
            $message = "Your document '{$this->document->title}' was uploaded successfully and is now in transit.";
        } elseif ($this->recipientType === 'admin') {
            $message = "New document '{$this->document->title}' has been uploaded by {$this->sender} and registered in the system.";
        }

        $dueDateStr = null;
        if ($this->document->due_date) {
            $dueDateStr = $this->document->due_date instanceof \Carbon\Carbon
                ? $this->document->due_date->toIso8601String()
                : \Carbon\Carbon::parse($this->document->due_date)->toIso8601String();
        }

        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'tracking_number' => $this->document->tracking_number ?? $this->document->qr_id,
            'sender' => $this->sender ?? 'System',
            'date_routed' => now()->toIso8601String(),
            'priority' => $this->document->priority,
            'due_date' => $dueDateStr,
            'message' => $message,
            'type' => 'document_routed',
            'read_at' => null,
        ];
    }

    public function toMail($notifiable)
    {
        $subject = "NAAP Document Routing System - Action Required: Document Routed - {$this->document->title}";
        $intro = "A new document has been routed to you.";
        
        if ($this->recipientType === 'uploader') {
            $subject = "NAAP Document Routing System - Upload Success: {$this->document->title}";
            $intro = "Your document has been successfully uploaded and is now routing.";
        } elseif ($this->recipientType === 'admin') {
            $subject = "NAAP Document Routing System - Admin Alert: New Document Uploaded";
            $intro = "A new document has been uploaded to the system by {$this->sender}.";
        }

        try {
            \Illuminate\Support\Facades\Log::channel('email')->info("Routing Email notification sent to {$notifiable->email} with subject: {$subject}");
        } catch (\Throwable $e) {
            // Logging failure should not abort mail dispatch
        }

        $dueDateFormatted = 'N/A';
        if ($this->document->due_date) {
            $dueDateFormatted = $this->document->due_date instanceof \Carbon\Carbon
                ? $this->document->due_date->format('M j, Y')
                : \Carbon\Carbon::parse($this->document->due_date)->format('M j, Y');
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($intro)
            ->line("Document: {$this->document->title}")
            ->line("Tracking Number: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Sender: " . ($this->sender ?? 'System'))
            ->line("Date Routed: " . now()->format('M j, Y h:i A'))
            ->line("Priority: {$this->document->priority}")
            ->line("Due Date: " . $dueDateFormatted);

        if ($this->otp && $this->recipientType === 'receiver') {
            $mail->line("🔒 SECURITY ACCESS PIN/OTP: **{$this->otp}**")
                 ->line("This document is marked as confidential. You must use this OTP/PIN to unlock and view the document details or download the file.");
        }

        $mail->action('View Document', url(route('track.detail', $this->document->id) . '?from_notification=1'))
             ->line('Thank you for using NAAP Routing System!');

        return $mail;
    }
}
