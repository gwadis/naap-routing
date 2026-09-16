<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;

class DocumentDueNotification extends Notification
{
    use Queueable;

    protected $document;

    /**
     * Create a new notification instance.
     */
    public function __construct($document)
    {
        $this->document = $document;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('NAAP Document Routing System - SLA Alert: Document Due Soon - ' . $this->document->title)
                    ->level('warning')
                    ->markdown('emails.documents.document_due', [
                        'document' => $this->document,
                        'url' => url('/documents/' . $this->document->id),
                        'notifiable' => $notifiable,
                    ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'title' => $this->document->title,
            'sla' => $this->document->sla,
            'priority' => $this->document->priority,
            'status' => $this->document->status,
            'due_date' => $this->document->due_date?->format('Y-m-d'),
            'url' => url('/documents/' . $this->document->id),
        ];
    }
}