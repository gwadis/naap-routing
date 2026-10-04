<?php

namespace App\Services;

use App\Models\User;
use App\Models\Document;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramService
{
    public const STATUS_CONNECTED      = 'connected';
    public const STATUS_DISCONNECTED   = 'disconnected';
    public const STATUS_PENDING        = 'pending';

    public const OUTCOME_SENT          = 'TELEGRAM_SENT';
    public const OUTCOME_FAILED        = 'TELEGRAM_FAILED';
    public const OUTCOME_UNCONFIGURED  = 'TELEGRAM_UNCONFIGURED';
    public const OUTCOME_NOT_CONNECTED = 'TELEGRAM_NOT_CONNECTED';

    /**
     * Check if the Telegram Bot Token is configured.
     */
    public function isConfigured(): bool
    {
        return !empty(config('services.telegram.bot_token'));
    }

    /**
     * Get the configured Telegram Bot username.
     */
    public function getBotUsername(): string
    {
        return config('services.telegram.bot_username', 'NAAPRoutingBot');
    }

    /**
     * Generate a short-lived, single-use connection token and Telegram deep link for the user.
     * Connection token expires in 15 minutes and is strictly single-use.
     */
    public function generateConnectionToken(User $user): array
    {
        $token = Str::random(32);

        $user->update([
            'telegram_connect_token' => $token,
            'telegram_connect_token_expires_at' => now()->addMinutes(15),
            'telegram_connection_status' => self::STATUS_PENDING,
        ]);

        $botUsername = $this->getBotUsername();
        $deepLink = "https://t.me/{$botUsername}?start={$token}";

        return [
            'token' => $token,
            'deep_link' => $deepLink,
            'expires_at' => $user->telegram_connect_token_expires_at,
        ];
    }

    /**
     * Connect a user via single-use token provided to Telegram bot `/start <token>`.
     */
    public function connectUserWithToken(string $token, string $chatId, ?string $username = null, ?string $displayName = null): ?User
    {
        $user = User::where('telegram_connect_token', $token)
            ->where('telegram_connect_token_expires_at', '>=', now())
            ->first();

        if (!$user) {
            return null;
        }

        $tgIdentifier = !empty($username) ? ('@' . ltrim($username, '@')) : ($displayName ?: ('ID: ' . $chatId));

        $user->update([
            'telegram_chat_id' => (string) $chatId,
            'telegram_username' => $tgIdentifier,
            'telegram_connected_at' => now(),
            'telegram_connection_status' => self::STATUS_CONNECTED,
            // Invalidate token immediately (single-use)
            'telegram_connect_token' => null,
            'telegram_connect_token_expires_at' => null,
        ]);

        try {
            ActivityLog::log(
                'Telegram Connected',
                null,
                [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'telegram_username' => $tgIdentifier,
                ]
            );
        } catch (\Throwable $e) {
            // Non-blocking log catch
        }

        // Send a friendly confirmation message through the bot
        $welcomeMsg = "✈️ <b>NAAP Document Routing System</b>\n\n"
                    . "Hello <b>" . htmlspecialchars($user->name) . "</b>,\n\n"
                    . "Your Telegram account has been linked successfully to the NAAP Document Routing System.\n\n"
                    . "You will now receive:\n"
                    . "• Important System Announcements\n"
                    . "• Maintenance & Emergency Notices\n"
                    . "• Document & Workflow Alerts\n\n"
                    . "<i>You can manage your notification preferences anytime from your NAAP Profile.</i>";

        $this->sendMessage($chatId, $welcomeMsg);

        return $user;
    }

    /**
     * Disconnect a user's Telegram connection.
     */
    public function disconnectUser(User $user): bool
    {
        $oldIdentifier = $user->telegram_username;

        $user->update([
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_connected_at' => null,
            'telegram_connection_status' => self::STATUS_DISCONNECTED,
            'telegram_connect_token' => null,
            'telegram_connect_token_expires_at' => null,
        ]);

        try {
            ActivityLog::log(
                'Telegram Disconnected',
                null,
                [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'previous_telegram_username' => $oldIdentifier,
                ]
            );
        } catch (\Throwable $e) {
            // Non-blocking log catch
        }

        return true;
    }

    /**
     * Send a raw Telegram message via official Bot API.
     * Never crashes document routing or workflow.
     */
    public function sendMessage(string $chatId, string $htmlMessage, ?array $replyMarkup = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => self::OUTCOME_UNCONFIGURED,
                'info' => 'Telegram Bot Token is not configured in server environment.',
            ];
        }

        $botToken = config('services.telegram.bot_token');
        $endpoint = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $payload = [
            'chat_id' => $chatId,
            'text' => $htmlMessage,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = Http::timeout(8)->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'status' => self::OUTCOME_SENT,
                    'message_id' => $data['result']['message_id'] ?? null,
                    'info' => 'Telegram message sent successfully.',
                ];
            }

            $errorMsg = $response->json('description') ?? ('HTTP ' . $response->status());
            Log::warning("[TelegramService] Telegram API error: {$errorMsg}");

            return [
                'success' => false,
                'status' => self::OUTCOME_FAILED,
                'info' => $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::warning("[TelegramService] Telegram dispatch exception: " . $e->getMessage());

            return [
                'success' => false,
                'status' => self::OUTCOME_FAILED,
                'info' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a document or workflow notification to a specific user.
     * Strictly enforces security:
     * - Never sends actual files
     * - Never sends confidential document preview or contents
     * - Never sends OTP or PIN
     */
    public function sendDocumentNotification(
        User $user,
        Document $document,
        string $eventTitle,
        string $actionRequired,
        bool $isConfidential = false,
        bool $isUrgent = false
    ): array {
        if (!$user->isTelegramConnected()) {
            return [
                'success' => false,
                'status' => self::OUTCOME_NOT_CONNECTED,
                'info' => 'User does not have Telegram connected.',
            ];
        }

        // Check user preferences
        if (!$user->telegram_notif_documents && !$isUrgent) {
            return [
                'success' => false,
                'status' => 'SKIPPED_PREFERENCE',
                'info' => 'User disabled document notifications in Telegram preferences.',
            ];
        }

        if ($isUrgent && !$user->telegram_notif_urgent) {
            return [
                'success' => false,
                'status' => 'SKIPPED_PREFERENCE',
                'info' => 'User disabled urgent notifications in Telegram preferences.',
            ];
        }

        $tracking = $document->tracking_number ?? $document->qr_id ?? ('DOC-' . $document->id);
        $naapUrl = url('/track?tracking_number=' . $tracking);

        if ($isConfidential) {
            // High Security Confidential Notification: NO sensitive content or PIN
            $msg = "🔒 <b>NAAP SECURITY NOTIFICATION</b>\n\n"
                 . "A <b>confidential document</b> requires your attention.\n\n"
                 . "<b>Tracking ID:</b> <code>{$tracking}</code>\n"
                 . "<b>Status:</b> Requires secure verification in NAAP\n\n"
                 . "<i>Confidential content and access credentials are never transmitted through Telegram. Please log in to NAAP and verify via your registered Email OTP to view this document.</i>\n\n"
                 . "👉 <a href=\"{$naapUrl}\">Open NAAP Secure Portal</a>";
        } else {
            $docTitle = htmlspecialchars(\Str::limit($document->title, 40));
            $header = $isUrgent ? "🚨 <b>NAAP URGENT WORKFLOW ALERT</b>" : "📄 <b>NAAP DOCUMENT NOTIFICATION</b>";

            $msg = "{$header}\n\n"
                 . "<b>" . htmlspecialchars($eventTitle) . "</b>\n\n"
                 . "<b>Document:</b> {$docTitle}\n"
                 . "<b>Tracking No.:</b> <code>{$tracking}</code>\n"
                 . "<b>Action Required:</b> " . htmlspecialchars($actionRequired) . "\n\n"
                 . "👉 <a href=\"{$naapUrl}\">Review in NAAP System</a>";
        }

        $result = $this->sendMessage($user->telegram_chat_id, $msg);

        try {
            ActivityLog::log(
                $result['success'] ? 'Telegram Notification Sent' : 'Telegram Notification Failed',
                $document->id,
                [
                    'user_id' => $user->id,
                    'event' => $eventTitle,
                    'is_confidential' => $isConfidential,
                    'is_urgent' => $isUrgent,
                    'success' => $result['success'],
                ]
            );
        } catch (\Throwable $e) {
            // Non-blocking log catch
        }

        return $result;
    }

    /**
     * Send an administrative announcement to connected Telegram users based on intended audience.
     */
    public function sendAnnouncement(
        string $title,
        string $message,
        string $audience = 'all',
        ?int $officeId = null,
        ?string $link = null
    ): array {
        $link = $link ?: url('/dashboard');

        // Build target recipient query
        $query = User::where('telegram_connection_status', self::STATUS_CONNECTED)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_notif_announcements', true);

        if ($audience === 'admins') {
            $query->whereIn('role', ['ADMIN', 'Administrator', 'Super Administrator']);
        } elseif ($audience === 'staff') {
            $query->whereIn('role', ['Staff', 'Office Head', 'Employee']);
        } elseif ($audience === 'office' && $officeId) {
            $query->where('office_id', $officeId);
        }

        $recipients = $query->get();

        $announcementMsg = "📢 <b>NAAP SYSTEM ANNOUNCEMENT</b>\n\n"
                         . "<b>" . htmlspecialchars($title) . "</b>\n\n"
                         . htmlspecialchars($message) . "\n\n"
                         . "👉 <a href=\"{$link}\">Open NAAP Portal</a>";

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            $res = $this->sendMessage($recipient->telegram_chat_id, $announcementMsg);
            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        try {
            ActivityLog::log(
                'Telegram Announcement Sent',
                null,
                [
                    'title' => $title,
                    'audience' => $audience,
                    'office_id' => $officeId,
                    'recipients_count' => $recipients->count(),
                    'sent' => $sentCount,
                    'failed' => $failedCount,
                ]
            );
        } catch (\Throwable $e) {
            // Non-blocking log catch
        }

        return [
            'total' => $recipients->count(),
            'sent' => $sentCount,
            'failed' => $failedCount,
        ];
    }

    /**
     * Handle incoming webhook updates from Telegram Bot API.
     */
    public function handleWebhookUpdate(array $update): array
    {
        $message = $update['message'] ?? null;
        if (!$message) {
            return ['ok' => true, 'action' => 'ignored'];
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim($message['text'] ?? '');
        $from = $message['from'] ?? [];
        $username = $from['username'] ?? null;
        $displayName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));

        // Handle /start with token: `/start <token>`
        if (str_starts_with($text, '/start')) {
            $parts = explode(' ', $text, 2);
            $token = isset($parts[1]) ? trim($parts[1]) : null;

            if ($token) {
                $connectedUser = $this->connectUserWithToken($token, $chatId, $username, $displayName);
                if ($connectedUser) {
                    return [
                        'ok' => true,
                        'action' => 'connected',
                        'user_id' => $connectedUser->id,
                    ];
                }

                $this->sendMessage(
                    $chatId,
                    "⚠️ <b>Link Expired or Invalid</b>\n\n"
                    . "This connection token is invalid or has already expired.\n"
                    . "Please return to your <b>NAAP Profile</b> and click <b>Connect Telegram</b> to generate a fresh link."
                );

                return ['ok' => true, 'action' => 'token_invalid'];
            }

            // Simple /start without token
            $this->sendMessage(
                $chatId,
                "✈️ <b>Welcome to NAAP Routing Bot</b>\n\n"
                . "This bot delivers important system announcements and document routing notices.\n\n"
                . "To link your NAAP account:\n"
                . "1. Log in to the NAAP Document Routing System.\n"
                . "2. Open your <b>Profile / Notification Settings</b>.\n"
                . "3. Click <b>Connect Telegram</b>."
            );

            return ['ok' => true, 'action' => 'welcome_sent'];
        }

        // Handle /status command
        if ($text === '/status') {
            $user = User::where('telegram_chat_id', $chatId)
                ->where('telegram_connection_status', self::STATUS_CONNECTED)
                ->first();

            if ($user) {
                $this->sendMessage(
                    $chatId,
                    "✅ <b>Telegram Connection Active</b>\n\n"
                    . "Linked NAAP Account: <b>" . htmlspecialchars($user->name) . "</b>\n"
                    . "Email: <code>" . htmlspecialchars($user->email) . "</code>\n"
                    . "Connected Since: " . $user->telegram_connected_at?->format('M j, Y h:i A')
                );
            } else {
                $this->sendMessage(
                    $chatId,
                    "ℹ️ <b>Not Connected</b>\n\n"
                    . "This Telegram chat is not currently connected to any NAAP account.\n"
                    . "Please log in to NAAP and click <b>Connect Telegram</b> in your profile."
                );
            }

            return ['ok' => true, 'action' => 'status_checked'];
        }

        // Handle /help command
        if ($text === '/help') {
            $this->sendMessage(
                $chatId,
                "ℹ️ <b>NAAP Telegram Bot Help</b>\n\n"
                . "<b>Commands:</b>\n"
                . "/start - Connect or start the bot\n"
                . "/status - Check connection status with NAAP\n"
                . "/help - Show this guide\n\n"
                . "<i>Note: Actual document actions, approvals, and signatures must be performed inside the secure NAAP system.</i>"
            );

            return ['ok' => true, 'action' => 'help_sent'];
        }

        // Prevent/inform users when they try to reply or chat with the broadcast bot
        $this->sendMessage(
            $chatId,
            "ℹ️ <b>Automated Notification Channel</b>\n\n"
            . "This bot is strictly for official outbound NAAP announcements and document notifications.\n\n"
            . "Direct replies are not monitored. To review, track, or sign documents, please log in to the NAAP Portal:\n"
            . "👉 <a href=\"" . url('/dashboard') . "\">" . url('/dashboard') . "</a>"
        );

        return ['ok' => true, 'action' => 'unhandled_text'];
    }

    /**
     * Poll recent updates from Telegram Bot API (useful for local development without public webhooks).
     */
    public function pollUpdates(): int
    {
        if (!$this->isConfigured()) {
            return 0;
        }

        $botToken = config('services.telegram.bot_token');
        $endpoint = "https://api.telegram.org/bot{$botToken}/getUpdates";

        try {
            $response = Http::timeout(3)->get($endpoint, [
                'limit' => 10,
                'allowed_updates' => ['message'],
            ]);

            if (!$response->successful()) {
                return 0;
            }

            $updates = $response->json('result') ?? [];
            if (empty($updates)) {
                return 0;
            }

            $processed = 0;
            $maxUpdateId = 0;

            foreach ($updates as $update) {
                $this->handleWebhookUpdate($update);
                $processed++;
                if (isset($update['update_id']) && $update['update_id'] > $maxUpdateId) {
                    $maxUpdateId = $update['update_id'];
                }
            }

            // Acknowledge updates by setting offset
            if ($maxUpdateId > 0) {
                Http::timeout(3)->get($endpoint, ['offset' => $maxUpdateId + 1]);
            }

            return $processed;
        } catch (\Throwable $e) {
            Log::debug("[TelegramService] Polling exception: " . $e->getMessage());
            return 0;
        }
    }
}
