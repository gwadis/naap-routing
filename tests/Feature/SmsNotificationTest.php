<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Services\SmsService;
use App\Services\NotificationService;
use App\Services\SmsProviders\SemaphoreProvider;
use App\Channels\SmsChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Notifications\Notification;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Office $office;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Office::create([
            'name' => 'Office of the President',
            'department' => 'Administration',
        ]);

        $this->user = User::create([
            'name' => 'Gladys Marmol',
            'email' => 'marmolgladys7@gmail.com',
            'phone' => '09690222557',
            'role' => 'Administrator',
            'office_id' => $this->office->id,
            'password' => bcrypt('Admin@12345'),
            'status' => 'Active',
            'needs_password_change' => false,
        ]);

        $this->document = Document::create([
            'title' => 'Executive Memorandum 2026',
            'type' => 'Memorandum',
            'priority' => 'High',
            'tracking_number' => 'TRK-20260927-D488E2',
            'status' => 'Pending',
            'origin_office_id' => $this->office->id,
            'current_office_id' => $this->office->id,
            'uploaded_by' => $this->user->id,
        ]);
    }

    /**
     * Test 1: Phone number normalization accurately standardizes Philippine mobile numbers.
     */
    public function test_phone_number_normalization_for_philippine_formats(): void
    {
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('09690222557'));
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('+639690222557'));
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('639690222557'));
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('9690222557'));
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('0969-022-2557'));
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('0969 022 2557'));
        $this->assertEquals('09690222557', SmsService::normalizePhoneNumber('(0969) 022-2557'));

        // International E.164
        $this->assertEquals('+639690222557', SmsService::toE164('09690222557'));
        $this->assertEquals('+639690222557', SmsService::toE164('+639690222557'));

        // Validation
        $this->assertTrue(SmsService::isValidPhilippineNumber('09690222557'));
        $this->assertTrue(SmsService::isValidPhilippineNumber('+639690222557'));
        $this->assertFalse(SmsService::isValidPhilippineNumber('12345'));
    }

    /**
     * Test 2: When SMS is unconfigured (zero-cost default), system does NOT fake SMS sent.
     * Clearly reports SMS_UNAVAILABLE without throwing errors or breaking workflow.
     */
    public function test_unconfigured_sms_reports_unavailable_and_does_not_fake_success(): void
    {
        config(['services.sms.enabled' => false]);
        $service = new SmsService();

        $this->assertFalse($service->isConfigured());
        $result = $service->send('09690222557', 'Test Alert');

        $this->assertFalse($result['success']);
        $this->assertEquals('SMS_UNAVAILABLE', $result['status']);
        $this->assertStringContainsString('no SMS provider configured', $result['info']);
        $this->assertNotNull($result['device_sms_uri']);
        $this->assertEquals('Open SMS', $result['draft_label']);
    }

    /**
     * Test 3: Device SMS URI generates standard RFC 5724 draft link for manual send.
     */
    public function test_device_sms_uri_generation(): void
    {
        $uri = SmsService::getDeviceSmsUri('09690222557', 'NAAP Routing Alert: Document TRK-1234');
        $this->assertNotNull($uri);
        $this->assertStringStartsWith('sms:09690222557?body=', $uri);
        $this->assertStringContainsString('NAAP%20Routing%20Alert', $uri);

        $draft = SmsService::createSmsDraft('09690222557', 'Document requires attention');
        $this->assertEquals('SMS_DRAFT_CREATED', $draft['status']);
        $this->assertEquals('Open SMS', $draft['label']);
    }

    /**
     * Test 4: Semaphore provider sends HTTP POST to Semaphore API when configured.
     */
    public function test_semaphore_provider_sends_http_request(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                [
                    'message_id' => 9876543,
                    'user_id' => 101,
                    'user' => 'Gladys Marmol',
                    'recipient' => '09690222557',
                    'message' => 'Hello from NAAP',
                    'status' => 'Queued',
                    'network' => 'Smart',
                ]
            ], 200),
        ]);

        $provider = new SemaphoreProvider('test_semaphore_api_key', 'NAAP');
        $result = $provider->send('09690222557', 'Hello from NAAP');

        $this->assertTrue($result['success']);
        $this->assertEquals('semaphore', $result['provider']);
        $this->assertEquals('9876543', $result['message_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && $request['number'] === '09690222557'
                && $request['apikey'] === 'test_semaphore_api_key'
                && $request['message'] === 'Hello from NAAP';
        });
    }

    /**
     * Test 5: Semaphore provider gracefully handles API error responses.
     */
    public function test_semaphore_provider_handles_api_errors(): void
    {
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                'error' => 'Insufficient credit balance.'
            ], 400),
        ]);

        $provider = new SemaphoreProvider('test_semaphore_api_key', 'NAAP');
        $result = $provider->send('09690222557', 'Hello from NAAP');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient credit balance', $result['info']);
    }

    /**
     * Test 6: Core workflow notifications rely strictly on In-App and Email, NOT SMS.
     */
    public function test_core_routing_notifications_do_not_depend_on_sms(): void
    {
        $routedNotif = new \App\Notifications\DocumentRoutedNotification($this->document, 'Dean Office', 'receiver');
        $via = $routedNotif->via($this->user);

        // Core channels only
        $this->assertContains('database', $via);
        $this->assertContains('mail', $via);
        $this->assertNotContains(\App\Channels\SmsChannel::class, $via);

        $receivedNotif = new \App\Notifications\DocumentReceivedNotification($this->document, 'Officer Santos', 'Registrar');
        $this->assertNotContains(\App\Channels\SmsChannel::class, $receivedNotif->via($this->user));

        $signedNotif = new \App\Notifications\DocumentSignedNotification($this->document, 'Dr. Perez');
        $this->assertNotContains(\App\Channels\SmsChannel::class, $signedNotif->via($this->user));
    }

    /**
     * Test 7: ConfidentialDocumentPinNotification relies on Email/In-App without SMS requirement.
     */
    public function test_confidential_document_pin_does_not_require_sms(): void
    {
        $notif = new \App\Notifications\ConfidentialDocumentPinNotification($this->document, 'President Office', '849201');
        $via = $notif->via($this->user);

        $this->assertNotContains(\App\Channels\SmsChannel::class, $via);
        $this->assertContains(\App\Channels\BrevoMailChannel::class, $via);
    }

    /**
     * Test 8: NotificationService abstraction handles standard workflow and emergency fallback.
     */
    public function test_notification_service_emergency_workflow(): void
    {
        config(['services.sms.enabled' => false]);
        $service = app(NotificationService::class);

        $result = $service->sendEmergency(
            $this->user,
            $this->document,
            'SLA processing deadline exceeded'
        );

        $this->assertEquals(NotificationService::OUTCOME_IN_APP_SENT, $result['in_app']);
        $this->assertEquals(NotificationService::OUTCOME_EMAIL_SENT, $result['email']);
        $this->assertEquals(NotificationService::OUTCOME_SMS_UNAVAILABLE, $result['sms_status']);
        $this->assertNotNull($result['device_sms_uri']);
    }

    /**
     * Test 9: Artisan command sms:send safely reports SMS_UNAVAILABLE when unconfigured.
     */
    public function test_artisan_sms_send_command_executes_safely_when_unconfigured(): void
    {
        config(['services.sms.enabled' => false]);

        $this->artisan('sms:send', [
            'phone' => '09690222557',
            'message' => 'Emergency test alert for Gladys Marmol',
        ])
        ->expectsOutputToContain('NAAP Enterprise Notification & SMS Fallback')
        ->expectsOutputToContain('SMS UNAVAILABLE')
        ->expectsOutputToContain('Open SMS')
        ->assertExitCode(0);
    }

    /**
     * Test 10: SmsChannel exits cleanly and does not fail when SMS is unconfigured.
     */
    public function test_sms_channel_safely_returns_unavailable_when_unconfigured(): void
    {
        config(['services.sms.enabled' => false]);
        $channel = app(SmsChannel::class);

        $notification = new class extends Notification {
            public function toSms($notifiable): string {
                return "Notification via SMS for {$notifiable->name}";
            }
        };

        $result = $channel->send($this->user, $notification);
        $this->assertNotNull($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('SMS_UNAVAILABLE', $result['status']);
    }

    /**
     * Test 11: Settings test-sms endpoint reports SMS_UNAVAILABLE with Open SMS draft link.
     */
    public function test_settings_test_sms_endpoint_returns_unavailable_with_draft_link(): void
    {
        config(['services.sms.enabled' => false]);

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => 'Administrator',
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->postJson(route('settings.test-sms'), [
            'phone' => '09690222557',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'status' => 'SMS_UNAVAILABLE',
            'draft_label' => 'Open SMS',
        ]);
        $this->assertNotNull($response->json('device_sms_uri'));
    }

    /**
     * Test 12: User can update phone in profile without external verification or third party login.
     */
    public function test_user_can_update_phone_without_external_verification(): void
    {
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->post(route('profile.update'), [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => '0969-022-2557',
        ]);

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertEquals('09690222557', $this->user->phone);
    }
}
