<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\DocumentPin;
use App\Models\ActivityLog;
use App\Notifications\ConfidentialDocumentPinNotification;
use App\Notifications\DocumentRoutedNotification;

class ConfidentialPinNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }


    public function test_confidential_document_pin_is_generated_on_open_and_sent_to_recipient_only()
    {
        Notification::fake();

        $originOffice = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $stepOffice = Office::create(['name' => 'VPAA Office', 'department' => 'VPAA']);
        $finalOffice = Office::create(['name' => 'Finance Office', 'department' => 'Finance']);

        $uploader = User::create([
            'name' => 'HR Uploader',
            'username' => 'hr_uploader',
            'email' => 'uploader@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'HR',
            'needs_password_change' => false,
            'office_id' => $originOffice->id,
        ]);

        $stepUser = User::create([
            'name' => 'VPAA Staff',
            'username' => 'vpaa_staff',
            'email' => 'vpaa_staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'VPAA',
            'office_id' => $stepOffice->id,
        ]);

        $finalUser = User::create([
            'name' => 'Finance Staff',
            'username' => 'finance_staff',
            'email' => 'finance_staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Finance',
            'office_id' => $finalOffice->id,
        ]);

        $mockFile = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        // Create document (should not send PIN notification yet)
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name
        ])->post(route('documents.store'), [
            'title' => 'Test Document Title',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'category' => 'Operational Plans',
            'description' => 'Test document description',
            'origin_office_id' => $originOffice->id,
            'destination_office_id' => $finalOffice->id,
            'routing_office_ids' => [$stepOffice->id, $finalOffice->id],
            'routing_user_ids' => [$stepUser->id, $finalUser->id],
            'routing_approval_types' => ['sequential', 'sequential'],
            'routing_signatures_required' => [1, 1],
            'file' => $mockFile,
            'is_confidential' => 1
        ]);

        $response->assertRedirect(route('documents.index'));

        // Assert no PIN notification is sent to any user yet
        Notification::assertNotSentTo($finalUser, ConfidentialDocumentPinNotification::class);
        Notification::assertNotSentTo($stepUser, ConfidentialDocumentPinNotification::class);
        Notification::assertNotSentTo($uploader, ConfidentialDocumentPinNotification::class);

        $document = Document::latest()->first();

        // 1. Uploader attempts to view the document. No PIN should be generated for them.
        $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name
        ])->get(route('track.detail', $document->id));

        $this->assertEquals(0, DocumentPin::count());

        // 2. Active receiver (VPAA Staff) scans the QR code. PIN should be generated & emailed.
        $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('qr.scan'), [
            'qr_data' => (string) $document->id
        ]);

        $this->assertEquals(1, DocumentPin::count());
        $pinRecord = DocumentPin::first();
        $this->assertEquals($stepUser->id, $pinRecord->user_id);
        $this->assertFalse($pinRecord->is_used);

        Notification::assertSentTo($stepUser, ConfidentialDocumentPinNotification::class, function ($notification) use ($pinRecord) {
            return $notification->pin === $pinRecord->pin;
        });

        // 3. Visiting again should reuse the same PIN (not generate/email a new one)
        Notification::fake();
        $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->get(route('track.detail', $document->id));

        $this->assertEquals(1, DocumentPin::count());
        Notification::assertNotSentTo($stepUser, ConfidentialDocumentPinNotification::class);

        // 4. Clicking "Resend PIN" should invalidate previous PIN, generate a new one, and email it.
        $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('documents.regeneratePin', $document->id));

        $this->assertEquals(2, DocumentPin::count());
        $newPinRecord = DocumentPin::orderBy('id', 'desc')->first();
        $this->assertNotEquals($pinRecord->pin, $newPinRecord->pin);

        // Assert old PIN is marked as used (invalidated)
        $this->assertTrue($pinRecord->fresh()->is_used);

        Notification::assertSentTo($stepUser, ConfidentialDocumentPinNotification::class, function ($notification) use ($newPinRecord) {
            return $notification->pin === $newPinRecord->pin;
        });
    }

    public function test_confidential_document_pin_verification_and_attempts_limit()
    {
        $originOffice = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $destOffice = Office::create(['name' => 'Finance Office', 'department' => 'Finance']);

        $uploader = User::create([
            'name' => 'HR Uploader',
            'username' => 'hr_uploader',
            'email' => 'uploader@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'HR',
            'needs_password_change' => false,
            'office_id' => $originOffice->id,
        ]);

        $stepUser = User::create([
            'name' => 'VPAA Staff',
            'username' => 'vpaa_staff',
            'email' => 'vpaa_staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'VPAA',
            'office_id' => $destOffice->id,
        ]);

        $mockFile = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        // Create document
        $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name
        ])->post(route('documents.store'), [
            'title' => 'Test Document Title',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'category' => 'Operational Plans',
            'description' => 'Test document description',
            'origin_office_id' => $originOffice->id,
            'destination_office_id' => $destOffice->id,
            'routing_office_ids' => [$destOffice->id],
            'routing_user_ids' => [$stepUser->id],
            'routing_approval_types' => ['sequential'],
            'routing_signatures_required' => [1],
            'file' => $mockFile,
            'is_confidential' => 1
        ]);

        $document = Document::latest()->first();

        // Scan the QR code as active receiver to generate PIN
        $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('qr.scan'), [
            'qr_data' => (string) $document->id
        ]);

        $pinRecord = DocumentPin::first();

        // Verify that entering an incorrect PIN fails and increments attempts
        for ($i = 1; $i <= 4; $i++) {
            $response = $this->withSession([
                'authenticated' => true,
                'user_id' => $stepUser->id,
                'user_role' => 'VPAA',
                'user_name' => $stepUser->name
            ])->post(route('qr.scan'), [
                'qr_data' => (string) $document->id,
                'pin' => '000000'
            ]);

            $response->assertStatus(422);
            $response->assertJson([
                'success' => false,
                'message' => 'Incorrect PIN code. Please try again.'
            ]);
            $this->assertEquals($i, $pinRecord->fresh()->attempts);
        }

        // The 5th failed attempt should trigger maximum attempts error
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('qr.scan'), [
            'qr_data' => (string) $document->id,
            'pin' => '000000'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Incorrect PIN code. Maximum attempts reached. A new PIN is required.'
        ]);
        $this->assertEquals(5, $pinRecord->fresh()->attempts);

        // Verification with correct PIN should now fail since attempts limit was reached
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('qr.scan'), [
            'qr_data' => (string) $document->id,
            'pin' => $pinRecord->pin
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Maximum failed attempts reached. Please request a new PIN.'
        ]);

        // Request a new PIN
        $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('documents.regeneratePin', $document->id));

        $newPinRecord = DocumentPin::orderBy('id', 'desc')->first();
        $this->assertNotEquals($pinRecord->pin, $newPinRecord->pin);

        // Verify with the correct new PIN
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $stepUser->id,
            'user_role' => 'VPAA',
            'user_name' => $stepUser->name
        ])->post(route('qr.scan'), [
            'qr_data' => (string) $document->id,
            'pin' => $newPinRecord->pin
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);

        $this->assertTrue($newPinRecord->fresh()->is_used);

        // Check audit log for verification event
        $this->assertDatabaseHas('activity_logs', [
            'document_id' => $document->id,
            'action' => 'Confidential PIN Verified',
            'user' => $stepUser->name
        ]);
    }

    public function test_application_uses_manila_timezone()
    {
        $this->assertEquals('Asia/Manila', config('app.timezone'));
    }
}
