<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\ActivityLog;
use App\Models\DocumentView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class EnterpriseDocumentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff1;
    private User $staff2;
    private Office $originOffice;
    private Office $hrOffice;
    private Office $recordsOffice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originOffice = Office::create([
            'name' => 'Office of the President',
            'department' => 'OP',
        ]);

        $this->hrOffice = Office::create([
            'name' => 'Human Resources',
            'department' => 'HR',
        ]);

        $this->recordsOffice = Office::create([
            'name' => 'Records Office',
            'department' => 'RO',
        ]);

        $this->admin = User::create([
            'name' => 'System Admin',
            'username' => 'sysadmin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('AdminPass123!'),
            'role' => 'Administrator',
            'office_id' => $this->originOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staff1 = User::create([
            'name' => 'Shane Muesco',
            'username' => 'smuesco',
            'email' => 'smuesco@naap.org',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->hrOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staff2 = User::create([
            'name' => 'John Records',
            'username' => 'jrecords',
            'email' => 'jrecords@naap.org',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->recordsOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);
    }

    private function actingAsUser(User $user)
    {
        return $this->withSession([
            'authenticated'    => true,
            'user_id'          => $user->id,
            'user_role'        => $user->role,
            'user_name'        => $user->name,
            'otp_verified'     => true,
            'password_changed' => true,
        ])->actingAs($user);
    }

    /**
     * Test: Document Details renders real identity, location, and breadcrumbs.
     */
    public function test_document_details_renders_real_identity_and_current_location(): void
    {
        $doc = Document::create([
            'title'                 => 'HR Policy Memo 2026',
            'tracking_number'       => 'TRK-20261003-HR01',
            'type'                  => 'PDF',
            'category'              => 'Memorandum',
            'description'           => 'Annual employee policy updates and guidelines',
            'priority'              => 'High',
            'status'                => 'Pending',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->hrOffice->id,
            'destination_office_id' => $this->recordsOffice->id,
            'receiver_user_id'      => $this->staff1->id,
            'uploaded_by'           => $this->admin->id,
            'due_date'              => now()->addDays(3),
            'qr_code'               => 'TRK-20261003-HR01',
            'qr_status'             => 'Generated',
        ]);

        // Hop 1: routed to HR
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->originOffice->id,
            'to_office_id'     => $this->hrOffice->id,
            'sender_user_id'   => $this->admin->id,
            'receiver_user_id' => $this->staff1->id,
            'status'           => 'Pending',
            'sort_order'       => 1,
            'created_at'       => now()->subHour(),
        ]);

        // Activity log
        ActivityLog::create([
            'action'      => 'Document Uploaded',
            'user'        => $this->admin->name,
            'document_id' => $doc->id,
            'created_at'  => now()->subHour(),
            'meta'        => ['office' => 'Office of the President'],
        ]);

        $response = $this->actingAsUser($this->admin)->get(route('documents.show', $doc->id));

        $response->assertStatus(200);
        $response->assertSee('NAAP Enterprise');
        $response->assertSee('Documents');
        $response->assertSee('Document Details');
        $response->assertSee('HR Policy Memo 2026');
        $response->assertSee('TRK-20261003-HR01');
        $response->assertSee('Memorandum');
        $response->assertSee('Annual employee policy updates and guidelines');
        $response->assertSee('Office of the President');
        $response->assertSee('Human Resources');
        $response->assertSee('Shane Muesco');
        $response->assertSee('Records Office');
        $response->assertSee('Document Passport');
        $response->assertSee('Print QR Label');
        $response->assertSee('Back to Documents');
    }

    /**
     * Test: Routing history and multi-hop journey display.
     */
    public function test_document_details_routing_history_and_journey(): void
    {
        $doc = Document::create([
            'title'                 => 'Procurement Requisition Form',
            'tracking_number'       => 'TRK-20261003-PROC',
            'type'                  => 'DOCX',
            'category'              => 'Requisition',
            'status'                => 'Under Review',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->hrOffice->id,
            'destination_office_id' => $this->recordsOffice->id,
            'receiver_user_id'      => $this->staff1->id,
            'uploaded_by'           => $this->admin->id,
            'due_date'              => now()->addHours(24),
        ]);

        // Hop 1: Completed
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->originOffice->id,
            'to_office_id'     => $this->hrOffice->id,
            'sender_user_id'   => $this->admin->id,
            'receiver_user_id' => $this->staff1->id,
            'status'           => 'Received',
            'sort_order'       => 1,
            'received_at'      => now()->subHours(2),
            'released_at'      => now()->subHour(),
            'notes'            => 'Initial review completed',
        ]);

        // Hop 2: Pending
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->hrOffice->id,
            'to_office_id'     => $this->recordsOffice->id,
            'sender_user_id'   => $this->staff1->id,
            'receiver_user_id' => $this->staff2->id,
            'status'           => 'Pending',
            'sort_order'       => 2,
            'created_at'       => now()->subHour(),
        ]);

        $response = $this->actingAsUser($this->staff1)->get(route('documents.show', $doc->id));

        $response->assertStatus(200);
        $response->assertSee('Initial review completed');
        $response->assertSee('John Records');
        $response->assertSee('2 Total Routing Hops');
        $response->assertSee('Nearing SLA');
    }

    /**
     * Test: Completed document reflects final state and processing time.
     */
    public function test_document_details_completed_state(): void
    {
        $created = now()->subDays(2);
        $completed = now()->subDay();

        $doc = Document::create([
            'title'                 => 'Completed Contract Agreement',
            'tracking_number'       => 'TRK-20261003-CMP01',
            'type'                  => 'PDF',
            'status'                => 'Completed',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->recordsOffice->id,
            'destination_office_id' => $this->recordsOffice->id,
            'receiver_user_id'      => $this->staff2->id,
            'uploaded_by'           => $this->admin->id,
            'completed_at'          => now(),
            'due_date'              => now()->addDays(5),
        ]);
        $doc->timestamps = false;
        $doc->created_at = now()->subDays(2);
        $doc->save();

        $response = $this->actingAsUser($this->admin)->get(route('documents.show', $doc->id));

        $response->assertStatus(200);
        $response->assertSee('Completed Within SLA');
        $response->assertSee('Workflow Completed &amp; Archived', false);
        $response->assertSee('Final Destination Reached');
    }

    /**
     * Test: Confidential document indicates restricted badge.
     */
    public function test_confidential_document_displays_security_notice(): void
    {
        $doc = Document::create([
            'title'                 => 'Executive Audit Report',
            'tracking_number'       => 'TRK-20261003-CONF',
            'type'                  => 'PDF',
            'status'                => 'Pending',
            'is_confidential'       => true,
            'access_pin'            => '1234',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->originOffice->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $response = $this->actingAsUser($this->admin)->get(route('documents.show', $doc->id));

        $response->assertStatus(200);
        $response->assertSee('Confidential Document');
        $response->assertSee('Regenerate PIN');
    }

    /**
     * Test: Origin office staff has view access (200 OK) but read-only mode when active custody is elsewhere.
     */
    public function test_unauthorized_user_sees_read_only_mode(): void
    {
        $originStaff = User::create([
            'name' => 'Origin Observer Staff',
            'username' => 'orig_staff',
            'email' => 'orig@naap.org',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->originOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $doc = Document::create([
            'title'                 => 'Restricted Department Review',
            'tracking_number'       => 'TRK-20261003-REST',
            'type'                  => 'PDF',
            'status'                => 'Pending',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->hrOffice->id,
            'destination_office_id' => $this->recordsOffice->id,
            'receiver_user_id'      => $this->staff1->id, // Shane Muesco is handler
            'uploaded_by'           => $this->admin->id,
        ]);

        // Origin staff can view the document (200), but cannot perform workflow actions (view-only)
        $response = $this->actingAsUser($originStaff)->get(route('documents.show', $doc->id));

        $response->assertStatus(200);
        $response->assertSee('View Only');
        $response->assertSee('Action Restricted (View Only)');
    }

    /**
     * Test: Read receipts / views history is recorded and rendered.
     */
    public function test_document_views_recorded_and_displayed(): void
    {
        $doc = Document::create([
            'title'                 => 'Public Memo',
            'tracking_number'       => 'TRK-20261003-VIEW',
            'type'                  => 'PDF',
            'status'                => 'Pending',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->hrOffice->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // Create a view log
        DocumentView::create([
            'document_id' => $doc->id,
            'user_id'     => $this->staff1->id,
            'office_id'   => $this->hrOffice->id,
            'viewed_at'   => now(),
        ]);

        $response = $this->actingAsUser($this->admin)->get(route('documents.show', $doc->id));

        $response->assertStatus(200);
        $response->assertSee('Read Receipts (Views)');
        $response->assertSee('Shane Muesco');
        $response->assertSee('1 View(s)');
    }
}
