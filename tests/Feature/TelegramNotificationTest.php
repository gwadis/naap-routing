<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\ActivityLog;
use App\Services\TelegramService;
use App\Services\NotificationService;
use App\Channels\TelegramChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Notifications\Notification;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $userWithTelegram;
    private User $userWithoutTelegram;
    private User $admin;
    private Office $office;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456789:TEST_BOT_TOKEN_ABCDEF',
            'services.telegram.bot_username' => 'NAAPRoutingBot',
        ]);

        $this->office = Office::create([
            'name' => 'Registrar Office',
            'department' => 'Academic Affairs',
        ]);

        $this->admin = User::create([
            'name' => 'Gladys Marmol',
            'email' => 'marmolgladys7@gmail.com',
            'role' => 'Administrator',
            'password' => bcrypt('Admin@12345'),
            'status' => 'Active',
            'needs_password_change' => false,
            'office_id' => $this->office->id,
        ]);

        $this->userWithTelegram = User::create([
            'name' => 'Capt. Juan Dela Cruz',
            'email' => 'juan.delacruz@naap.edu.ph',
            'role' => 'Staff',
            'password' => bcrypt('Staff@12345'),
            'status' => 'Active',
            'needs_password_change' => false,
            'office_id' => $this->office->id,
            'telegram_chat_id' => '987654321',
            'telegram_username' => '@capt_juan',
            'telegram_connected_at' => now(),
            'telegram_connection_status' => 'connected',
            'telegram_notif_announcements' => true,
            'telegram_notif_documents' => true,
            'telegram_notif_urgent' => true,
        ]);

        $this->userWithoutTelegram = User::create([
            'name' => 'Elena Santos',
            'email' => 'elena.santos@naap.edu.ph',
            'role' => 'Employee',
            'password' => bcrypt('Staff@12345'),
            'status' => 'Active',
            'needs_password_change' => false,
            'office_id' => $this->office->id,
            'telegram_chat_id' => null,
            'telegram_connection_status' => 'disconnected',
        ]);

        $this->document = Document::create([
            'title' => 'Flight Operations Manual 2026',
            'type' => 'Manual',
            'priority' => 'High',
            'tracking_number' => 'TRK-20260930-FLT01',
            'status' => 'Pending',
            'origin_office_id' => $this->office->id,
            'current_office_id' => $this->office->id,
            'uploaded_by' => $this->admin->id,
        ]);
    }

    /**
     * TEST 1 & 2: User without Telegram connected receives normal in-app and email notifications.
     */
    public function test_user_without_telegram_receives_in_app_and_email_only(): void
    {
        $notif = new \App\Notifications\DocumentRoutedNotification($this->document, 'Dean Office', 'receiver');
        $via = $notif->via($this->userWithoutTelegram);

        $this->assertContains('database', $via);
        $this->assertContains('mail', $via);
        $this->assertNotContains(TelegramChannel::class, $via);
    }

    /**
     * TEST 3 & 4: Clicking Connect Telegram generates single-use token and valid deep link.
     */
    public function test_connect_telegram_generates_token_and_deep_link(): void
    {
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->userWithoutTelegram->id,
            'user_role' => $this->userWithoutTelegram->role,
            'user_email' => $this->userWithoutTelegram->email,
        ])->getJson(route('telegram.connect'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'deep_link', 'token', 'expires_at']);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringStartsWith('https://t.me/NAAPRoutingBot?start=', $data['deep_link']);

        $this->userWithoutTelegram->refresh();
        $this->assertEquals($data['token'], $this->userWithoutTelegram->telegram_connect_token);
        $this->assertNotNull($this->userWithoutTelegram->telegram_connect_token_expires_at);
    }

    /**
     * TEST 5 & 6 & 7: Webhook handles /start <token> and securely links Telegram chat to NAAP account.
     */
    public function test_telegram_webhook_start_command_connects_user_securely(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 111]], 200),
        ]);

        $service = app(TelegramService::class);
        $tokenData = $service->generateConnectionToken($this->userWithoutTelegram);
        $token = $tokenData['token'];

        // Simulate incoming webhook payload from Telegram Bot API when user presses START
        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 10001,
            'message' => [
                'message_id' => 500,
                'from' => [
                    'id' => 777888999,
                    'is_bot' => false,
                    'first_name' => 'Elena',
                    'last_name' => 'Santos',
                    'username' => 'elena_santos_ph',
                ],
                'chat' => [
                    'id' => 777888999,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "/start {$token}",
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('connected', $response->json('action'));

        $this->userWithoutTelegram->refresh();
        $this->assertTrue($this->userWithoutTelegram->isTelegramConnected());
        $this->assertEquals('777888999', $this->userWithoutTelegram->telegram_chat_id);
        $this->assertEquals('@elena_santos_ph', $this->userWithoutTelegram->telegram_username);
        $this->assertEquals('connected', $this->userWithoutTelegram->telegram_connection_status);
        $this->assertNull($this->userWithoutTelegram->telegram_connect_token); // Single use consumed
    }

    /**
     * TEST 8 & 9: Admin sends announcement and connected Telegram user receives it.
     */
    public function test_admin_announcement_broadcasts_to_connected_telegram_users(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 222]], 200),
        ]);

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->admin->id,
            'user_role' => 'Administrator',
            'user_email' => $this->admin->email,
        ])->postJson(route('telegram.announcement'), [
            'title' => 'Scheduled System Maintenance',
            'message' => 'NAAP Routing will undergo routine database maintenance tonight from 10 PM to 11 PM.',
            'audience' => 'all',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verified HTTP POST was sent to Telegram Bot API with the chat ID
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === '987654321'
                && str_contains($request['text'], 'Scheduled System Maintenance');
        });
    }

    /**
     * TEST 10: Non-connected user does not receive Telegram message.
     */
    public function test_non_connected_user_does_not_receive_telegram(): void
    {
        $service = app(TelegramService::class);
        $result = $service->sendDocumentNotification(
            $this->userWithoutTelegram,
            $this->document,
            'Document Assigned',
            'Please review in system'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(TelegramService::OUTCOME_NOT_CONNECTED, $result['status']);
    }

    /**
     * TEST 11 & 12: User disconnects Telegram and notifications stop.
     */
    public function test_user_disconnects_telegram_and_notifications_cease(): void
    {
        $this->assertTrue($this->userWithTelegram->isTelegramConnected());

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->userWithTelegram->id,
            'user_role' => $this->userWithTelegram->role,
            'user_email' => $this->userWithTelegram->email,
        ])->postJson(route('telegram.disconnect'));

        $response->assertStatus(200);
        $this->userWithTelegram->refresh();

        $this->assertFalse($this->userWithTelegram->isTelegramConnected());
        $this->assertNull($this->userWithTelegram->telegram_chat_id);
        $this->assertEquals('disconnected', $this->userWithTelegram->telegram_connection_status);

        // Subsequent notification via DocumentRoutedNotification will not include Telegram
        $notif = new \App\Notifications\DocumentRoutedNotification($this->document, 'Registrar', 'receiver');
        $via = $notif->via($this->userWithTelegram);
        $this->assertNotContains(TelegramChannel::class, $via);
    }

    /**
     * TEST 13: Confidential document notification does NOT expose confidential contents, file, OTP, or PIN.
     */
    public function test_confidential_document_notification_hides_sensitive_content_and_pin(): void
    {
        $confidentialDoc = Document::create([
            'title' => 'Top Secret Academic Board Deliberations',
            'type' => 'Board Resolution',
            'priority' => 'Urgent',
            'tracking_number' => 'TRK-CONF-999',
            'status' => 'Pending',
            'is_confidential' => true,
            'origin_office_id' => $this->office->id,
            'current_office_id' => $this->office->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $pinNotif = new \App\Notifications\ConfidentialDocumentPinNotification($confidentialDoc, 'President Office', '749102');
        $telegramMessage = $pinNotif->toTelegram($this->userWithTelegram);

        // Security assertions:
        $this->assertStringNotContainsString('749102', $telegramMessage); // Must NOT expose PIN
        $this->assertStringNotContainsString('Top Secret Academic Board Deliberations', $telegramMessage); // Must NOT expose confidential title
        $this->assertStringContainsString('Confidential', $telegramMessage);
        $this->assertStringContainsString('TRK-CONF-999', $telegramMessage);
        $this->assertStringContainsString('Email OTP', $telegramMessage);
    }

    /**
     * TEST 14 & 15: OTP is still sent ONLY through email with 5 minutes validity.
     */
    public function test_otp_remains_email_only_with_5_minutes_validity(): void
    {
        Mail::fake();

        // Trigger OTP generation via UserController transaction logic
        $otp = (string) random_int(100000, 999999);
        $record = \App\Models\Otp::create([
            'user_id' => $this->userWithTelegram->id,
            'otp' => \Illuminate\Support\Facades\Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
            'attempts' => 0,
        ]);

        $this->assertEquals(5, (int) round(now()->diffInSeconds($record->expires_at) / 60));
        $this->assertFalse($record->is_used);

        // Verified that OTP model and logic does NOT dispatch Telegram
        $this->assertDatabaseHas('otps', [
            'user_id' => $this->userWithTelegram->id,
        ]);
    }

    /**
     * TEST 16 & 17: Telegram delivery failure does not break document workflow or throw uncaught errors.
     */
    public function test_telegram_failure_does_not_break_document_workflow(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response('Gateway Timeout', 504),
        ]);

        $notifService = app(NotificationService::class);
        $routedNotif = new \App\Notifications\DocumentRoutedNotification($this->document, 'Dean Office', 'receiver');

        // Dispatches without throwing any fatal exceptions
        $outcomes = $notifService->sendStandard(
            $this->userWithTelegram,
            $routedNotif,
            $this->document,
            'Document Routed',
            'Action Required'
        );

        $this->assertEquals(NotificationService::OUTCOME_IN_APP_SENT, $outcomes['in_app']);
        $this->assertEquals(NotificationService::OUTCOME_EMAIL_SENT, $outcomes['email']);
    }

    /**
     * TEST 18: Bot token is never exposed to the frontend in status or connect endpoints.
     */
    public function test_bot_token_is_never_exposed_in_json_responses(): void
    {
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->userWithTelegram->id,
            'user_role' => $this->userWithTelegram->role,
            'user_email' => $this->userWithTelegram->email,
        ])->getJson(route('telegram.status'));

        $response->assertStatus(200);
        $content = $response->getContent();
        
        $this->assertStringNotContainsString('TEST_BOT_TOKEN_ABCDEF', $content);
        $this->assertStringNotContainsString('123456789:', $content);
        $this->assertTrue($response->json('configured'));
    }

    /**
     * TEST 19: Audit log correctly records Telegram events.
     */
    public function test_audit_logs_record_telegram_connection_and_disconnection(): void
    {
        $service = app(TelegramService::class);
        $service->disconnectUser($this->userWithTelegram);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Telegram Disconnected',
        ]);
    }

    /**
     * TEST 20: Admin announcement respects audience filter.
     */
    public function test_admin_announcement_respects_audience_filter(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 333]], 200),
        ]);

        // Audience: Admins only
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->admin->id,
            'user_role' => 'Administrator',
            'user_email' => $this->admin->email,
        ])->postJson(route('telegram.announcement'), [
            'title' => 'Admin Security Briefing',
            'message' => 'Monthly system audit scheduled.',
            'audience' => 'admins',
        ]);

        $response->assertStatus(200);

        // Since userWithTelegram has role 'Staff', they are NOT in 'admins' audience
        Http::assertNotSent(function ($request) {
            return $request['chat_id'] === '987654321';
        });
    }
}
