<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use Illuminate\Support\Facades\Log;

class QRScanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private $originOffice;
    private $destOffice;
    private $otherOffice;
    private $creatorUser;
    private $receiverUser;
    private $scanningUser; // Not active receiver, not in routing
    private $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originOffice = Office::create(['name' => 'Registrar Office', 'department' => 'Academics']);
        $this->destOffice = Office::create(['name' => 'Accounting Office', 'department' => 'Finance']);
        $this->otherOffice = Office::create(['name' => 'Library', 'department' => 'Student Services']);

        $this->creatorUser = User::create([
            'name' => 'Prof. Creator',
            'username' => 'prof_creator',
            'email' => 'creator@naap.edu',
            'password' => bcrypt('password'),
            'role' => 'FACULTY',
            'office_id' => $this->originOffice->id,
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        $this->receiverUser = User::create([
            'name' => 'Accountant Receiver',
            'username' => 'accountant_receiver',
            'email' => 'receiver@naap.edu',
            'password' => bcrypt('password'),
            'role' => 'STAFF',
            'office_id' => $this->destOffice->id,
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        // Scanning user is a completely different user (e.g. library staff)
        $this->scanningUser = User::create([
            'name' => 'Librarian Scanner',
            'username' => 'librarian_scanner',
            'email' => 'librarian@naap.edu',
            'password' => bcrypt('password'),
            'role' => 'STAFF',
            'office_id' => $this->otherOffice->id,
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        $this->document = Document::create([
            'title' => 'Curriculum Proposal',
            'description' => 'Proposal for updated curriculum',
            'type' => 'PDF',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->destOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'uploaded_by' => $this->creatorUser->id,
            'status' => 'Pending',
            'is_confidential' => false,
        ]);

        DocumentRouting::create([
            'document_id' => $this->document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'signature_required' => false,
        ]);
    }

    private function loginUser(User $user)
    {
        return $this->withSession([
            'authenticated' => true,
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_name' => $user->name,
        ])->actingAs($user);
    }

    /**
     * Test 1: Any user can scan QR code to locate and validate document without being blocked by 403.
     */
    public function test_any_user_can_scan_qr_code_without_workflow_authorization_error()
    {
        // Librarian scanner is NOT the active receiver or creator
        $response = $this->loginUser($this->scanningUser)
            ->postJson(route('qr.scan'), [
                'qr_data' => (string) $this->document->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'document' => [
                'id' => $this->document->id,
                'title' => 'Curriculum Proposal',
            ]
        ]);
    }

    /**
     * Test 2: Inactive/cancelled document returns 410 error.
     */
    public function test_inactive_cancelled_document_returns_410()
    {
        $this->document->update(['status' => 'Cancelled']);

        $response = $this->loginUser($this->scanningUser)
            ->postJson(route('qr.scan'), [
                'qr_data' => (string) $this->document->id,
            ]);

        $response->assertStatus(410);
        $response->assertJson([
            'success' => false,
            'error' => true,
        ]);
    }

    /**
     * Test 3: Confidential document scan requests PIN code.
     */
    public function test_confidential_document_scan_requests_pin_verification()
    {
        $this->document->update(['is_confidential' => true]);

        $response = $this->loginUser($this->scanningUser)
            ->postJson(route('qr.scan'), [
                'qr_data' => (string) $this->document->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'pin_required' => true,
            'success' => false,
        ]);
    }

    /**
     * Test 4: QR route /documents/{id}/workflow opens document details page first.
     */
    public function test_workflow_route_opens_document_details_page()
    {
        $response = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $this->document->id));

        $response->assertStatus(200);
        $response->assertSee('Curriculum Proposal');
        $response->assertSee('Workflow Execution Actions');
    }

    /**
     * Test 5: Scanned user can view document details, but action buttons remain disabled.
     */
    public function test_scanned_user_sees_document_details_with_actions_disabled()
    {
        // 1. User scans QR
        $this->loginUser($this->scanningUser)
            ->postJson(route('qr.scan'), [
                'qr_data' => (string) $this->document->id,
            ]);

        // 2. User opens document details
        $viewResponse = $this->loginUser($this->scanningUser)
            ->get(route('documents.show', $this->document->id));

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Action Restricted (View Only)');

        // 3. User attempts workflow action -> 403
        $actionResponse = $this->loginUser($this->scanningUser)
            ->post(route('documents.workflowAction', $this->document->id), [
                'status' => 'Received',
                'notes' => 'Unauthorized action attempt',
            ]);

        $actionResponse->assertStatus(403);
    }

    /**
     * Test 6: Debug log "QR Scan Access" is recorded.
     */
    public function test_qr_scan_access_is_logged()
    {
        Log::shouldReceive('info')
            ->atLeast()->once()
            ->withArgs(function ($message, $context = []) {
                return $message === 'QR Scan Access'
                    && isset($context['document_id'])
                    && $context['document_id'] == $this->document->id;
            });

        // Ignore other logs
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $this->loginUser($this->scanningUser)
            ->postJson(route('qr.scan'), [
                'qr_data' => (string) $this->document->id,
            ]);
    }
}
