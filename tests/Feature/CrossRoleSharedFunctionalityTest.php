<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class CrossRoleSharedFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $staffReceiver;
    protected $unauthorizedStaff;
    protected $originOffice;
    protected $targetOffice;
    protected $otherOffice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $this->originOffice = Office::create([
            'name'       => 'Office of the President',
            'department' => 'OP',
        ]);
        $this->targetOffice = Office::create([
            'name'       => 'Human Resources',
            'department' => 'HR',
        ]);
        $this->otherOffice = Office::create([
            'name'       => 'Legal Office',
            'department' => 'Legal',
        ]);

        $this->admin = User::create([
            'name'                  => 'Admin Commander',
            'username'              => 'admincommander',
            'email'                 => 'admin@naap.org',
            'password'              => bcrypt('SecretPassword123!'),
            'role'                  => 'Administrator',
            'office_id'             => $this->originOffice->id,
            'is_active'             => true,
            'needs_password_change' => false,
        ]);

        $this->staffReceiver = User::create([
            'name'                  => 'HR Specialist Jane',
            'username'              => 'hrspecialist',
            'email'                 => 'staff.hr@naap.org',
            'password'              => bcrypt('SecretPassword123!'),
            'role'                  => 'Staff',
            'office_id'             => $this->targetOffice->id,
            'signature'             => 'signatures/staff_jane.png',
            'is_active'             => true,
            'needs_password_change' => false,
        ]);

        $this->unauthorizedStaff = User::create([
            'name'                  => 'Legal Officer Bob',
            'username'              => 'legalofficer',
            'email'                 => 'staff.legal@naap.org',
            'password'              => bcrypt('SecretPassword123!'),
            'role'                  => 'Staff',
            'office_id'             => $this->otherOffice->id,
            'is_active'             => true,
            'needs_password_change' => false,
        ]);
    }

    private function actingAsAdmin()
    {
        return $this->withSession([
            'authenticated'    => true,
            'user_id'          => $this->admin->id,
            'user_role'        => 'Administrator',
            'user_name'        => $this->admin->name,
            'otp_verified'     => true,
            'password_changed' => true,
        ])->actingAs($this->admin);
    }

    private function actingAsStaff(User $user)
    {
        return $this->withSession([
            'authenticated'    => true,
            'user_id'          => $user->id,
            'user_role'        => 'Staff',
            'user_name'        => $user->name,
            'otp_verified'     => true,
            'password_changed' => true,
        ])->actingAs($user);
    }

    /**
     * Cross-Role End-to-End Workflow:
     * Admin creates & routes -> Staff receives, verifies QR & OTP, accepts & processes ->
     * Admin & Staff reflect the identical single source of truth.
     */
    public function test_cross_role_shared_workflow_and_security_consistency()
    {
        // 1. Admin uploads document
        $file = UploadedFile::fake()->create('Annual_HR_Staffing_2026.pdf', 1024, 'application/pdf');
        $uploadResp = $this->actingAsAdmin()->post(route('documents.store'), [
            'file'               => $file,
            'title'              => 'Annual HR Staffing Plan 2026',
            'description'        => 'Staffing review and resource allocation plan',
            'category'           => 'Administrative',
            'origin_office_id'   => $this->originOffice->id,
            'routing_office_ids' => [$this->targetOffice->id],
            'routing_user_ids'   => [$this->staffReceiver->id],
            'sla'                => 'Simple Transaction (3 Working Days)',
            'priority'           => 'Normal',
            'is_confidential'    => true,
        ]);

        $uploadResp->assertSessionHasNoErrors();
        $doc = Document::where('title', 'Annual HR Staffing Plan 2026')->first();
        $this->assertNotNull($doc);
        $this->assertNotEmpty($doc->tracking_number);

        // 2. Admin routes document to target office with staffReceiver as assigned recipient
        $routeResp = $this->actingAsAdmin()->post(route('routing.route', $doc->id), [
            'office_id'          => $this->targetOffice->id,
            'receiver_user_ids'  => [$this->staffReceiver->id],
            'notes'              => 'Please evaluate and process urgently',
        ]);

        $routeResp->assertRedirect();
        $doc->refresh();
        $this->assertEquals($this->targetOffice->id, $doc->current_office_id);
        $this->assertEquals($this->staffReceiver->id, $doc->receiver_user_id);
        $this->assertEquals('In Transit', $doc->status);

        // 3. Staff logs in -> Sees document in their documents list and staff dashboard
        $staffDocsResp = $this->actingAsStaff($this->staffReceiver)->get(route('documents.index'));

        $staffDocsResp->assertStatus(200);
        $staffDocsResp->assertSee('Annual HR Staffing Plan 2026');
        $staffDocsResp->assertSee('Human Resources');

        $staffDashResp = $this->actingAsStaff($this->staffReceiver)->get(route('dashboard'));

        $staffDashResp->assertStatus(200);
        $this->assertGreaterThan(0, $staffDashResp->viewData('totalDocs'));

        // 4. Unauthorized staff cannot view workflow or download confidential document
        $unauthorizedResp = $this->actingAsStaff($this->unauthorizedStaff)->get(route('documents.show', $doc->id));

        $unauthorizedResp->assertStatus(403);

        // 5. Staff Receiver scans QR -> Identified correctly without 403
        $qrResp = $this->actingAsStaff($this->staffReceiver)->postJson(route('qr.scan'), [
            'qr_data'            => $doc->tracking_number,
            'target_document_id' => $doc->id,
        ]);

        $qrResp->assertStatus(200);
        $qrData = $qrResp->json();
        // Confidential document triggers PIN required response
        $this->assertTrue($qrData['pin_required']);

        // 6. OTP/PIN verification for confidential document
        $pinRecord = \App\Models\DocumentPin::where('document_id', $doc->id)
            ->where('recipient_id', $this->staffReceiver->id)
            ->first();
        $this->assertNotNull($pinRecord);

        // Verify with legitimate PIN
        $verifyOtpResp = $this->actingAsStaff($this->staffReceiver)->withSession([
            'qr_verified_' . $doc->id => true,
        ])->postJson(route('qr.scan'), [
            'qr_data'            => $doc->tracking_number,
            'target_document_id' => $doc->id,
            'pin'                => $pinRecord->pin,
        ]);

        $verifyOtpResp->assertStatus(200);
        $this->assertTrue($verifyOtpResp->json('success'));

        // 7. Staff performs workflow action: Receives the document
        $receiveResp = $this->actingAsStaff($this->staffReceiver)->withSession([
            'qr_verified_' . $doc->id  => true,
            'otp_verified_' . $doc->id => true,
        ])->post(route('documents.workflowAction', $doc->id), [
            'status'           => 'Received',
            'notes'            => 'Physical file received in good order by HR',
            'signature_option' => 'profile',
        ]);

        $receiveResp->assertRedirect();
        $doc->refresh();
        $this->assertEquals('Received', $doc->status);
        $this->assertNotNull($doc->received_at);

        // 8. Staff completes the document with signature
        $completeResp = $this->actingAsStaff($this->staffReceiver)->withSession([
            'qr_verified_' . $doc->id  => true,
            'otp_verified_' . $doc->id => true,
        ])->post(route('documents.workflowAction', $doc->id), [
            'status'           => 'Completed',
            'notes'            => 'HR Review finalized and staffing plan approved',
            'signature_option' => 'profile',
        ]);

        $completeResp->assertRedirect();
        $doc->refresh();
        $this->assertEquals('Completed', $doc->status);
        $this->assertNotNull($doc->completed_at);
        $this->assertNotNull($doc->processed_at);

        // Verify chronological timestamp integrity: created <= received <= processed <= completed
        $this->assertTrue($doc->created_at->timestamp <= $doc->received_at->timestamp);
        $this->assertTrue($doc->received_at->timestamp <= $doc->processed_at->timestamp);
        $this->assertTrue($doc->processed_at->timestamp <= $doc->completed_at->timestamp);

        // 9. Verify ADMIN Documents page and Analytics reflect the EXACT same status and data
        $adminDocsResp = $this->actingAsAdmin()->get(route('documents.index', ['status' => 'completed']));

        $adminDocsResp->assertStatus(200);
        $adminDocsResp->assertSee('Annual HR Staffing Plan 2026');
        $adminDocsResp->assertSee('Completed');

        $adminDashResp = $this->actingAsAdmin()->get(route('dashboard'));

        $adminDashResp->assertStatus(200);
        $this->assertEquals(1, $adminDashResp->viewData('completedDocs'));

        // 10. Verify Audit Trail has logged events with real actors
        $latestLog = ActivityLog::where('document_id', $doc->id)
            ->where('action', 'Document Completed')
            ->first();
        $this->assertNotNull($latestLog);
        $this->assertEquals($this->staffReceiver->name, $latestLog->user);
    }
}
