<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Document;
use App\Services\SmsService;

class EmergencyDocumentAlertNotification extends Notification
{
    public Document $document;
    public string $reason;
    public ?string $originOfficeName;

    public function __construct(Document $document, string $reason, ?string $originOfficeName = null)
    {
        $this->document = $document;
        $this->reason = $reason;
        $this->originOfficeName = $originOfficeName;
    }

    /**
     * Determine notification channels.
     * Core channels: Database (In-App) and Mail (Email).
     * SMS is strictly an optional emergency adapter if enabled and configured.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        // SMS is strictly an optional emergency fallback
        if (config('services.sms.enabled', false) && app(SmsService::class)->isConfigured() && !empty($notifiable->phone)) {
            $channels[] = \App\Channels\SmsChannel::class;
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);

        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'tracking_number' => $tracking,
            'message' => "🚨 EMERGENCY ALERT for document '{$this->document->title}': {$this->reason}",
            'type' => 'emergency_alert',
            'priority' => 'Urgent',
            'read_at' => null,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);

        return (new MailMessage)
            ->error()
            ->subject("EMERGENCY DOCUMENT ALERT – {$this->document->title} [{$tracking}]")
            ->greeting("Hello {$notifiable->name},")
            ->line("🚨 **EMERGENCY ACTION REQUIRED**")
            ->line("An urgent condition has been flagged on document **{$this->document->title}**.")
            ->line("**Tracking Number:** {$tracking}")
            ->line("**Reason:** {$this->reason}")
            ->line("**Location:** " . ($this->originOfficeName ?? 'NAAP Document System'))
            ->action('View Document Immediately', url(route('track.detail', $this->document->id)))
            ->line('Please prioritize this document immediately.');
    }

    public function toSms($notifiable): string
    {
        $tracking = $this->document->tracking_number ?? $this->document->qr_id ?? ('DOC-' . $this->document->id);
        $title = \Illuminate\Support\Str::limit($this->document->title, 25);

        return "NAAP URGENT: Action required for [{$tracking}] '{$title}': {$this->reason}.";
    }
}
