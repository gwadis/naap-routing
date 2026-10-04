<?php

namespace App\Services;

use App\Models\User;
use App\Models\Document;
use App\Models\ActivityLog;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise Notification Service Abstraction
 *
 * Architecture:
 * Notification Service
 *         ├── In-App (Database notifications) [Core]
 *         ├── Email (SMTP/Brevo)              [Core]
 *         ├── Telegram (Bot API)              [Optional Channel]
 *         └── SMS (Provider Adapter)          [Optional Emergency Fallback]
 */
class NotificationService
{
    public const OUTCOME_IN_APP_SENT       = 'IN-APP NOTIFICATION SENT';
    public const OUTCOME_EMAIL_SENT        = 'EMAIL SENT';
    public const OUTCOME_SMS_SENT          = 'SMS SENT';
    public const OUTCOME_SMS_UNAVAILABLE   = 'SMS UNAVAILABLE';
    public const OUTCOME_SMS_DRAFT_CREATED = 'SMS DRAFT CREATED';
    public const OUTCOME_TELEGRAM_SENT     = 'TELEGRAM SENT';
    public const OUTCOME_TELEGRAM_SKIPPED  = 'TELEGRAM SKIPPED';

    protected SmsService $smsService;
    protected TelegramService $telegramService;

    public function __construct(SmsService $smsService, TelegramService $telegramService)
    {
        $this->smsService = $smsService;
        $this->telegramService = $telegramService;
    }

    /**
     * Send standard workflow notification (In-App + Email + Optional Telegram).
     * Normal workflow notifications NEVER depend on SMS.
     */
    public function sendStandard(
        User $user,
        Notification $notification,
        ?Document $document = null,
        ?string $eventTitle = null,
        ?string $actionRequired = null
    ): array {
        $outcomes = [
            'in_app' => null,
            'email' => null,
            'telegram' => self::OUTCOME_TELEGRAM_SKIPPED,
        ];

        // 1. In-App Notification (Core)
        try {
            $user->notify($notification);
            $outcomes['in_app'] = self::OUTCOME_IN_APP_SENT;
        } catch (\Throwable $e) {
            Log::warning("[NotificationService] Standard in-app dispatch error: " . $e->getMessage());
        }

        // 2. Email Notification (Core)
        if (!empty($user->email)) {
            $outcomes['email'] = self::OUTCOME_EMAIL_SENT;
        }

        // 3. Optional Telegram Notification (If user connected Telegram)
        if ($document && $user->isTelegramConnected() && $this->telegramService->isConfigured()) {
            try {
                $isConfidential = (bool) ($document->is_confidential ?? false);
                $title = $eventTitle ?? ('Update on Document ' . ($document->tracking_number ?? $document->id));
                $action = $actionRequired ?? 'Please view details and acknowledge in NAAP.';

                $tgRes = $this->telegramService->sendDocumentNotification(
                    $user,
                    $document,
                    $title,
                    $action,
                    $isConfidential,
                    false
                );

                if ($tgRes['success']) {
                    $outcomes['telegram'] = self::OUTCOME_TELEGRAM_SENT;
                }
            } catch (\Throwable $tgEx) {
                Log::warning("[NotificationService] Telegram notification dispatch error: " . $tgEx->getMessage());
            }
        }

        return $outcomes;
    }

    /**
     * Dispatch emergency / urgent notification workflow.
     *
     * Strict Workflow:
     * 1. Create In-App notification (Always).
     * 2. Send Email (Always).
     * 3. Dispatch Telegram alert if user has connected Telegram.
     * 4. Check whether an actual SMS provider is configured.
     * 5. IF configured & working: attempt SMS -> record SMS SENT.
     * 6. IF unavailable: record SMS UNAVAILABLE (and generate optional device draft link).
     * 7. Does NOT fail the document workflow or retry endlessly.
     */
    public function sendEmergency(User $user, Document $document, string $reason, ?string $customMessage = null): array
    {
        $results = [
            'in_app' => null,
            'email' => null,
            'telegram' => self::OUTCOME_TELEGRAM_SKIPPED,
            'sms' => null,
            'sms_status' => self::OUTCOME_SMS_UNAVAILABLE,
            'device_sms_uri' => null,
            'draft_label' => 'Open SMS',
        ];

        $tracking = $document->tracking_number ?? $document->qr_id ?? ('DOC-' . $document->id);
        $title = \Illuminate\Support\Str::limit($document->title, 30);
        $alertMessage = $customMessage ?? "NAAP URGENT: Action required for [{$tracking}] '{$title}': {$reason}.";

        // 1. In-App Notification (Always)
        try {
            $user->notify(new \App\Notifications\SystemNotification(
                "⚠️ URGENT ALERT: Action required for document '{$document->title}' ({$tracking}). Reason: {$reason}",
                url(route('track.detail', $document->id))
            ));
            $results['in_app'] = self::OUTCOME_IN_APP_SENT;
        } catch (\Throwable $e) {
            Log::warning("[NotificationService] Emergency in-app dispatch error: " . $e->getMessage());
        }

        // 2. Email Notification (Always)
        if (!empty($user->email)) {
            try {
                $user->notify(new \App\Notifications\DocumentDueNotification($document));
                $results['email'] = self::OUTCOME_EMAIL_SENT;
            } catch (\Throwable $e) {
                Log::warning("[NotificationService] Emergency email dispatch error: " . $e->getMessage());
            }
        }

        // 3. Optional Telegram Notification (If user connected Telegram)
        if ($user->isTelegramConnected() && $this->telegramService->isConfigured()) {
            try {
                $isConfidential = (bool) ($document->is_confidential ?? false);
                $tgRes = $this->telegramService->sendDocumentNotification(
                    $user,
                    $document,
                    "URGENT ACTION REQUIRED",
                    $reason,
                    $isConfidential,
                    true
                );

                if ($tgRes['success']) {
                    $results['telegram'] = self::OUTCOME_TELEGRAM_SENT;
                }
            } catch (\Throwable $tgEx) {
                Log::warning("[NotificationService] Telegram emergency alert error: " . $tgEx->getMessage());
            }
        }

        // 4. Optional SMS Fallback
        if ($this->smsService->isConfigured() && !empty($user->phone)) {
            $smsResult = $this->smsService->send($user->phone, $alertMessage);
            if ($smsResult['success']) {
                $results['sms'] = self::OUTCOME_SMS_SENT;
                $results['sms_status'] = self::OUTCOME_SMS_SENT;
            } else {
                $results['sms'] = self::OUTCOME_SMS_UNAVAILABLE;
                $results['sms_status'] = self::OUTCOME_SMS_UNAVAILABLE;
                $results['device_sms_uri'] = SmsService::getDeviceSmsUri($user->phone, $alertMessage);
            }
        } else {
            // SMS Provider is NOT configured (Zero-cost default)
            $results['sms'] = self::OUTCOME_SMS_UNAVAILABLE;
            $results['sms_status'] = self::OUTCOME_SMS_UNAVAILABLE;

            if (!empty($user->phone)) {
                $results['device_sms_uri'] = SmsService::getDeviceSmsUri($user->phone, $alertMessage);
                $results['sms_draft'] = self::OUTCOME_SMS_DRAFT_CREATED;
            }

            // Record audit trail without error
            try {
                ActivityLog::log(
                    'SMS Fallback Unavailable',
                    $document->id,
                    [
                        'recipient' => $user->phone ?? 'N/A',
                        'status' => self::OUTCOME_SMS_UNAVAILABLE,
                        'info' => 'SMS unavailable — no SMS provider configured. Core alert delivered via Email and In-App.',
                    ]
                );
            } catch (\Throwable $logEx) {
                // Non-blocking
            }
        }

        return $results;
    }
}
