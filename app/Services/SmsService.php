<?php

namespace App\Services;

use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\ActivityLog;
use App\Services\SmsProviders\NoneSmsProvider;
use App\Services\SmsProviders\LogSmsProvider;
use App\Services\SmsProviders\SemaphoreProvider;
use App\Services\SmsProviders\TwilioProvider;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Normalize any Philippine mobile number format into standard local format (09XXXXXXXXX).
     */
    public static function normalizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove spaces, hyphens, parentheses, dots
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));

        // +639XXXXXXXXX -> 09XXXXXXXXX
        if (str_starts_with($cleaned, '+63')) {
            $cleaned = '0' . substr($cleaned, 3);
        }
        // 639XXXXXXXXX -> 09XXXXXXXXX
        elseif (str_starts_with($cleaned, '63') && strlen($cleaned) === 12) {
            $cleaned = '0' . substr($cleaned, 2);
        }
        // 9XXXXXXXXX (10 digits) -> 09XXXXXXXXX
        elseif (str_starts_with($cleaned, '9') && strlen($cleaned) === 10) {
            $cleaned = '0' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Convert standard local number to international E.164 (+639XXXXXXXXX).
     */
    public static function toE164(?string $phone): ?string
    {
        $normalized = self::normalizePhoneNumber($phone);
        if (!$normalized || !self::isValidPhilippineNumber($normalized)) {
            return $normalized;
        }

        return '+63' . substr($normalized, 1);
    }

    /**
     * Validate if number matches standard Philippine mobile pattern (11 digits, starts with 09).
     */
    public static function isValidPhilippineNumber(?string $phone): bool
    {
        $normalized = self::normalizePhoneNumber($phone);
        return !empty($normalized) && preg_match('/^09\d{9}$/', $normalized) === 1;
    }

    /**
     * Check if SMS system is enabled. Default is false (zero cost).
     */
    public static function isEnabled(): bool
    {
        return (bool) config('services.sms.enabled', false);
    }

    /**
     * Check if a live, functional external SMS provider is actively configured with valid credentials.
     */
    public function isConfigured(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $driver = strtolower(config('services.sms.driver', 'none'));

        return match ($driver) {
            'semaphore' => !empty(config('services.sms.semaphore.api_key')),
            'twilio' => !empty(config('services.sms.twilio.sid')) && !empty(config('services.sms.twilio.token')),
            'log' => true,
            default => false,
        };
    }

    /**
     * Generate standard RFC 5724 device URI (`sms:09XXXXXXXXX?body=...`)
     * Allows user/admin to open native device SMS app to send manual SMS drafts at zero cost.
     */
    public static function getDeviceSmsUri(?string $phoneNumber, string $message): ?string
    {
        $normalized = self::normalizePhoneNumber($phoneNumber);
        if (empty($normalized)) {
            return null;
        }

        return 'sms:' . $normalized . '?body=' . rawurlencode($message);
    }

    /**
     * Create an optional device-based SMS draft structure.
     * Clearly labeled as a draft/manual send — NEVER labeled "SMS Sent".
     */
    public static function createSmsDraft(?string $phoneNumber, string $message): array
    {
        $normalized = self::normalizePhoneNumber($phoneNumber);

        return [
            'status' => 'SMS_DRAFT_CREATED',
            'label' => 'Open SMS',
            'recipient' => $normalized,
            'message' => $message,
            'device_sms_uri' => self::getDeviceSmsUri($phoneNumber, $message),
            'info' => 'SMS draft created — manual send via device messaging app required.',
        ];
    }

    /**
     * Resolve the appropriate SMS provider driver.
     */
    public function resolveProvider(?string $driver = null): SmsProviderInterface
    {
        $driver = strtolower($driver ?? config('services.sms.driver', 'none'));

        return match ($driver) {
            'semaphore' => !empty(config('services.sms.semaphore.api_key')) 
                ? new SemaphoreProvider() 
                : new NoneSmsProvider(),
            'twilio' => !empty(config('services.sms.twilio.sid')) 
                ? new TwilioProvider() 
                : new NoneSmsProvider(),
            'log' => new LogSmsProvider(),
            default => new NoneSmsProvider(),
        };
    }

    /**
     * Send an SMS to any recipient.
     * Never fakes SMS delivery. If no provider is configured, returns SMS_UNAVAILABLE without crashing.
     */
    public function send(string $phoneNumber, string $message, ?string $driver = null): array
    {
        $normalized = self::normalizePhoneNumber($phoneNumber);

        if (empty($normalized)) {
            return [
                'success' => false,
                'provider' => 'none',
                'status' => 'SMS_UNAVAILABLE',
                'message_id' => null,
                'recipient' => $phoneNumber,
                'info' => 'SMS unavailable — invalid or missing phone number.',
                'raw' => null,
            ];
        }

        // If no explicit driver requested and SMS is not configured, safely return SMS_UNAVAILABLE
        if ($driver === null && !$this->isConfigured()) {
            $draft = self::createSmsDraft($normalized, $message);
            return [
                'success' => false,
                'provider' => 'none',
                'status' => 'SMS_UNAVAILABLE',
                'message_id' => null,
                'recipient' => $normalized,
                'original_number' => $phoneNumber,
                'info' => 'SMS unavailable — no SMS provider configured.',
                'device_sms_uri' => $draft['device_sms_uri'],
                'draft_label' => 'Open SMS',
                'raw' => null,
            ];
        }

        $provider = $this->resolveProvider($driver);
        $result = $provider->send($normalized, $message);
        $result['recipient'] = $normalized;
        $result['original_number'] = $phoneNumber;
        $result['status'] = $result['success'] ? 'SMS_SENT' : ($result['status'] ?? 'SMS_UNAVAILABLE');

        // Audit log without throwing exceptions
        try {
            if ($result['success']) {
                ActivityLog::log(
                    'SMS Notification Sent',
                    null,
                    [
                        'recipient' => $normalized,
                        'provider' => $result['provider'],
                        'message_id' => $result['message_id'],
                        'status' => 'SMS_SENT',
                        'success' => true,
                    ]
                );
            } else {
                ActivityLog::log(
                    'SMS Fallback Unavailable',
                    null,
                    [
                        'recipient' => $normalized,
                        'provider' => $result['provider'],
                        'status' => 'SMS_UNAVAILABLE',
                        'info' => $result['info'] ?? 'SMS unavailable — no SMS provider configured.',
                        'success' => false,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Non-blocking log catch
        }

        return $result;
    }

    /**
     * Send status update alert for a document to a user.
     */
    public function sendDocumentStatusAlert(User $user, Document $document, string $status, ?string $notes = null): array
    {
        if (empty($user->phone)) {
            return [
                'success' => false,
                'status' => 'SMS_UNAVAILABLE',
                'info' => 'SMS unavailable — user has no mobile phone configured.',
                'recipient' => null,
            ];
        }

        $tracking = $document->tracking_number ?? $document->qr_id ?? ('DOC-' . $document->id);
        $title = \Str::limit($document->title, 25);
        $msg = "NAAP ALERT: Document [{$tracking}] '{$title}' is now {$status}.";

        if ($notes) {
            $msg .= ' ' . \Str::limit($notes, 40);
        }

        $msg .= " View: " . url('/track?tracking_number=' . $tracking);

        return $this->send($user->phone, $msg);
    }

    /**
     * Send new routing alert to office recipient.
     */
    public function sendDocumentRoutedAlert(User $recipient, Document $document, ?Office $fromOffice = null): array
    {
        if (empty($recipient->phone)) {
            return [
                'success' => false,
                'status' => 'SMS_UNAVAILABLE',
                'info' => 'SMS unavailable — recipient has no mobile phone configured.',
                'recipient' => null,
            ];
        }

        $tracking = $document->tracking_number ?? $document->qr_id ?? ('DOC-' . $document->id);
        $title = \Str::limit($document->title, 25);
        $fromName = $fromOffice?->name ?? 'another office';

        $msg = "NAAP NOTICE: New document [{$tracking}] '{$title}' routed to you by {$fromName}. Please acknowledge in system: " . url('/track?tracking_number=' . $tracking);

        return $this->send($recipient->phone, $msg);
    }

    /**
     * Send high priority / SLA breach alert.
     */
    public function sendUrgentAlert(User $user, Document $document, string $reason): array
    {
        if (empty($user->phone)) {
            return [
                'success' => false,
                'status' => 'SMS_UNAVAILABLE',
                'info' => 'SMS unavailable — user has no mobile phone configured.',
                'recipient' => null,
            ];
        }

        $tracking = $document->tracking_number ?? $document->qr_id ?? ('DOC-' . $document->id);
        $msg = "NAAP URGENT: Action required for [{$tracking}]: {$reason}. Immediate attention requested.";

        return $this->send($user->phone, $msg);
    }

    /**
     * Send OTP or 2FA verification code.
     * Note: Never logs the actual OTP plaintext.
     */
    public function sendOtp(User $user, string $otp): array
    {
        if (empty($user->phone)) {
            return [
                'success' => false,
                'status' => 'SMS_UNAVAILABLE',
                'info' => 'SMS unavailable — user has no mobile phone configured.',
                'recipient' => null,
            ];
        }

        $msg = "NAAP Security: Your verification code is {$otp}. Valid for 5 minutes. Do NOT share this code with anyone.";

        return $this->send($user->phone, $msg);
    }
}
