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
        return ['database', 'mail']; // Store in database and send email
    }

    public function toDatabase($notifiable)
    {
        $message = "Document '{$this->document->title}' has been routed to you";
        if ($this->recipientType === 'uploader') {
            $message = "Your document '{$this->document->title}' was uploaded successfully and is now in transit.";
        } elseif ($this->recipientType === 'admin') {
            $message = "New document '{$this->document->title}' has been uploaded by {$this->sender} and registered in the system.";
        }

        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'tracking_number' => $this->document->tracking_number ?? $this->document->qr_id,
            'sender' => $this->sender ?? 'System',
            'date_routed' => now()->toIso8601String(),
            'priority' => $this->document->priority,
            'due_date' => $this->document->due_date ? $this->document->due_date->toIso8601String() : null,
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

        \Illuminate\Support\Facades\Log::channel('email')->info("Routing Email notification sent to {$notifiable->email} with subject: {$subject}");

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($intro)
            ->line("Document: {$this->document->title}")
            ->line("Tracking Number: " . ($this->document->tracking_number ?? $this->document->qr_id))
            ->line("Sender: " . ($this->sender ?? 'System'))
            ->line("Date Routed: " . now()->format('M j, Y h:i A'))
            ->line("Priority: {$this->document->priority}")
            ->line("Due Date: " . ($this->document->due_date ? $this->document->due_date->format('M j, Y') : 'N/A'));

        if ($this->otp && $this->recipientType === 'receiver') {
            $mail->line("🔒 SECURITY ACCESS PIN/OTP: **{$this->otp}**")
                 ->line("This document is marked as confidential. You must use this OTP/PIN to unlock and view the document details or download the file.");
        }

        $mail->action('View Document', url(route('track.detail', $this->document->id) . '?from_notification=1'))
             ->line('Thank you for using NAAP Routing System!');

        return $mail;
    }
}
