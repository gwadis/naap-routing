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

class EnterpriseDocumentsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff1;
    private User $staff2;
    private Office $officeOrigin;
    private Office $officeCurrent;
    private Office $officeDest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeOrigin = Office::create([
            'name' => 'President Office',
            'department' => 'Executive',
        ]);

        $this->officeCurrent = Office::create([
            'name' => 'Human Resources Department',
            'department' => 'HR',
        ]);

        $this->officeDest = Office::create([
            'name' => 'Finance and Accounting',
            'department' => 'Finance',
        ]);

        $this->admin = User::create([
            'name' => 'Workspace Administrator',
            'username' => 'enterprise_admin',
            'email' => 'admin@enterprise.test',
            'password' => bcrypt('AdminPass123!'),
            'role' => 'Administrator',
            'office_id' => $this->officeOrigin->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staff1 = User::create([
            'name' => 'Shane Miller',
            'username' => 'shane_m',
            'email' => 'shane@enterprise.test',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeCurrent->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staff2 = User::create([
            'name' => 'Elena Vance',
            'username' => 'elena_v',
            'email' => 'elena@enterprise.test',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeDest->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);
    }

    private function asAdmin()
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

    private function asStaff(User $user)
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

    public function test_workspace_comprehensive_search_across_all_fields()
    {
        // 1. Target doc assigned to Shane Miller in HR
        $docShane = Document::create([
            'title'                 => 'Aviation Safety Review 2026',
            'tracking_number'       => 'TRK-2026-AVSAFE',
            'type'                  => 'pdf',
            'category'              => 'Accreditation',
            'priority'              => 'High',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeCurrent->id,
            'destination_office_id' => $this->officeDest->id,
            'receiver_user_id'      => $this->staff1->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // 2. Another doc with different title and receiver
        $docElena = Document::create([
            'title'                 => 'Semester Curriculum Budget Plan',
            'tracking_number'       => 'TRK-9999-BUDGET',
            'type'                  => 'docx',
            'category'              => 'Finance Related Documents',
            'priority'              => 'Normal',
            'status'                => 'In Transit',
            'origin_office_id'      => $this->officeCurrent->id,
            'current_office_id'     => $this->officeDest->id,
            'destination_office_id' => $this->officeDest->id,
            'receiver_user_id'      => $this->staff2->id,
            'uploaded_by'           => $this->staff2->id,
        ]);

        // Search by receiver first name "Shane"
        $resp = $this->asAdmin()->get('/documents?search=Shane');
        $resp->assertOk();
        $resp->assertSee('Aviation Safety Review 2026');
        $resp->assertDontSee('Semester Curriculum Budget Plan');

        // Search by tracking number partial "TRK-2026"
        $resp = $this->asAdmin()->get('/documents?search=TRK-2026');
        $resp->assertOk();
        $resp->assertSee('TRK-2026-AVSAFE');
        $resp->assertDontSee('TRK-9999-BUDGET');

        // Search by category "Accreditation"
        $resp = $this->asAdmin()->get('/documents?search=Accreditation');
        $resp->assertOk();
        $resp->assertSee('TRK-2026-AVSAFE');
        $resp->assertDontSee('TRK-9999-BUDGET');

        // Search by office "Human Resources"
        $resp = $this->asAdmin()->get('/documents?search=Human Resources');
        $resp->assertOk();
        $resp->assertSee('TRK-2026-AVSAFE');
    }

    public function test_workspace_advanced_filtering_by_status_category_confidential_and_sla()
    {
        // Confidential doc, overdue
        $docConfOverdue = Document::create([
            'title'                 => 'Confidential Audit Report',
            'tracking_number'       => 'CONF-OVERDUE-1',
            'type'                  => 'pdf',
            'category'              => 'Audit',
            'priority'              => 'Urgent',
            'status'                => 'Under Review',
            'is_confidential'       => true,
            'due_date'              => now()->subDays(2),
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeCurrent->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // Public doc, on track
        $docPublicOnTrack = Document::create([
            'title'                 => 'General Office Circular',
            'tracking_number'       => 'PUB-ONTRACK-2',
            'type'                  => 'docx',
            'category'              => 'Circular',
            'priority'              => 'Low',
            'status'                => 'Approved',
            'is_confidential'       => false,
            'due_date'              => now()->addDays(5),
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeCurrent->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // Filter by confidential=1
        $resp = $this->asAdmin()->get('/documents?is_confidential=1');
        $resp->assertOk();
        $resp->assertSee('CONF-OVERDUE-1');
        $resp->assertDontSee('PUB-ONTRACK-2');

        // Filter by SLA condition = overdue
        $resp = $this->asAdmin()->get('/documents?sla_condition=overdue');
        $resp->assertOk();
        $resp->assertSee('CONF-OVERDUE-1');
        $resp->assertDontSee('PUB-ONTRACK-2');

        // Filter by category = Circular
        $resp = $this->asAdmin()->get('/documents?category=Circular');
        $resp->assertOk();
        $resp->assertSee('PUB-ONTRACK-2');
        $resp->assertDontSee('CONF-OVERDUE-1');

        // Filter by status = approved
        $resp = $this->asAdmin()->get('/documents?status=approved');
        $resp->assertOk();
        $resp->assertSee('PUB-ONTRACK-2');
        $resp->assertDontSee('CONF-OVERDUE-1');
    }

    public function test_workspace_bulk_actions_assign_receiver_and_mark_viewed()
    {
        $doc1 = Document::create([
            'title'                 => 'Bulk Target Doc 1',
            'tracking_number'       => 'BLK-001',
            'type'                  => 'pdf',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeCurrent->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $doc2 = Document::create([
            'title'                 => 'Bulk Target Doc 2',
            'tracking_number'       => 'BLK-002',
            'type'                  => 'pdf',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeCurrent->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // 1. Bulk Assign Receiver
        $assignResp = $this->asAdmin()->postJson('/documents/bulk-action', [
            'action'           => 'assign_receiver',
            'document_ids'     => [$doc1->id, $doc2->id],
            'receiver_user_id' => $this->staff1->id,
        ]);

        $assignResp->assertOk();
        $assignResp->assertJson(['success' => true]);

        $doc1->refresh();
        $doc2->refresh();
        $this->assertEquals($this->staff1->id, $doc1->receiver_user_id);
        $this->assertEquals($this->staff1->id, $doc2->receiver_user_id);

        // Verify activity logged
        $this->assertTrue(ActivityLog::where('document_id', $doc1->id)->where('action', 'like', '%Receiver Assigned%')->exists());

        // 2. Bulk Mark as Viewed
        $viewResp = $this->asStaff($this->staff1)->postJson('/documents/bulk-action', [
            'action'       => 'mark_viewed',
            'document_ids' => [$doc1->id, $doc2->id],
        ]);

        $viewResp->assertOk();
        $viewResp->assertJson(['success' => true]);

        $this->assertTrue(DocumentView::where('document_id', $doc1->id)->where('user_id', $this->staff1->id)->exists());
        $this->assertTrue(DocumentView::where('document_id', $doc2->id)->where('user_id', $this->staff1->id)->exists());

        // 3. Bulk Change Status
        $statusResp = $this->asAdmin()->postJson('/documents/bulk-action', [
            'action'       => 'change_status',
            'document_ids' => [$doc1->id, $doc2->id],
            'new_status'   => 'Under Review',
        ]);

        $statusResp->assertOk();
        $doc1->refresh();
        $this->assertEquals('Under Review', $doc1->status);
    }

    public function test_workspace_bulk_actions_rbac_and_safety()
    {
        $doc = Document::create([
            'title'                 => 'Admin Protected Document',
            'tracking_number'       => 'ADM-PROT-1',
            'type'                  => 'pdf',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // Staff tries to bulk delete admin document -> forbidden
        $deleteResp = $this->asStaff($this->staff1)->postJson('/documents/bulk-action', [
            'action'       => 'delete',
            'document_ids' => [$doc->id],
        ]);

        $deleteResp->assertStatus(403);
        $this->assertDatabaseHas('documents', ['id' => $doc->id]);

        // Admin bulk delete with routing history -> safely archives document
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->officeOrigin->id,
            'to_office_id'     => $this->officeCurrent->id,
            'receiver_user_id' => $this->staff1->id,
            'status'           => 'Pending',
        ]);

        $adminDeleteResp = $this->asAdmin()->postJson('/documents/bulk-action', [
            'action'       => 'delete',
            'document_ids' => [$doc->id],
        ]);

        $adminDeleteResp->assertOk();
        $doc->refresh();
        $this->assertEquals('Archived', $doc->status);
        $this->assertNotNull($doc->archived_at);
    }

    public function test_sla_remaining_calculation_handles_missing_timestamps_accurately()
    {
        // Document without due date should show N/A
        $docNoDueDate = Document::create([
            'title'             => 'Document Without Due Date',
            'tracking_number'   => 'NO-DUE-1',
            'type'              => 'pdf',
            'status'            => 'Pending',
            'origin_office_id'  => $this->officeOrigin->id,
            'uploaded_by'       => $this->admin->id,
            'due_date'          => null,
        ]);

        $resp = $this->asAdmin()->get('/documents');
        $resp->assertOk();
        $resp->assertSee('NO-DUE-1');
        $resp->assertSee('sla-na');
    }
}
