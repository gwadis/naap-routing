<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $originOffice;
    protected Office $destOffice;
    protected Office $transitOffice;
    protected User $adminUser;
    protected User $creatorUser;
    protected User $receiverUser;
    protected User $destOfficeStaff;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originOffice = Office::create(['name' => 'Registrar Office', 'code' => 'REG']);
        $this->destOffice = Office::create(['name' => 'Accounting Office', 'code' => 'ACC']);
        $this->transitOffice = Office::create(['name' => 'Dean Office', 'code' => 'DEAN']);

        $this->adminUser = User::create([
            'name' => 'Administrator',
            'email' => 'admin@naap.org',
            'username' => 'sysadmin',
            'password' => 'password123',
            'role' => 'Administrator',
            'office_id' => $this->originOffice->id,
        ]);

        $this->creatorUser = User::create([
            'name' => 'Doc Creator',
            'email' => 'creator@naap.org',
            'username' => 'creator',
            'password' => 'password123',
            'role' => 'Staff',
            'office_id' => $this->originOffice->id,
        ]);

        $this->receiverUser = User::create([
            'name' => 'Assigned Receiver',
            'email' => 'receiver@naap.org',
            'username' => 'receiver',
            'password' => 'password123',
            'role' => 'Staff',
            'office_id' => $this->transitOffice->id,
        ]);

        $this->destOfficeStaff = User::create([
            'name' => 'Accounting Staff',
            'email' => 'acc_staff@naap.org',
            'username' => 'accstaff',
            'password' => 'password123',
            'role' => 'Staff',
            'office_id' => $this->destOffice->id,
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Stranger',
            'email' => 'stranger@naap.org',
            'username' => 'stranger',
            'password' => 'password123',
            'role' => 'Staff',
            'office_id' => Office::create(['name' => 'Unrelated Office', 'code' => 'UNREL'])->id,
        ]);
    }

    protected function loginUser(User $user)
    {
        return $this->actingAs($user)->withSession([
            'authenticated' => true,
            'user_id'       => $user->id,
            'user_role'     => $user->role,
            'user_name'     => $user->name,
        ]);
    }

    /**
     * Test: Document creator can VIEW workflow page, but action buttons are disabled if not active receiver.
     */
    public function test_document_creator_can_view_workflow_page()
    {
        $document = Document::create([
            'title' => 'Curriculum Revision',
            'type' => 'Curriculum',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'sender_user_id' => $this->creatorUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
        ]);

        // Creator opening /documents/{id}/workflow should succeed (200)
        $response = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $document->id));

        $response->assertStatus(200);
        $response->assertSee('Workflow Execution Actions');
        $response->assertSee('View Only'); // Fallback notice because creator is not current active receiver
    }

    /**
     * Test: Active assigned receiver can VIEW and PERFORM workflow action.
     */
    public function test_active_receiver_can_view_and_perform_workflow_action()
    {
        $document = Document::create([
            'title' => 'Grade Submission',
            'type' => 'Grading',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'signature_required' => false,
            'scanned_at' => now(),
        ]);

        // 1. Can view workflow
        $response = $this->loginUser($this->receiverUser)
            ->get(route('documents.workflow', $document->id));

        $response->assertStatus(200);
        $response->assertSee('Update State'); // Has active action button

        // 2. Can perform workflow action
        $postResponse = $this->loginUser($this->receiverUser)
            ->post(route('documents.workflowAction', $document->id), [
                'status' => 'Received',
                'notes' => 'Received by transit office.',
            ]);

        $postResponse->assertStatus(302);
        $document->refresh();
        $this->assertEquals('Received', $document->status);
    }

    /**
     * Test: Office-based routing where receiver_user_id is NULL allows office member to act.
     */
    public function test_office_based_routing_with_null_receiver_allows_office_staff_to_act()
    {
        $document = Document::create([
            'title' => 'Budget Request',
            'type' => 'Financial',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->destOffice->id,
            'receiver_user_id' => null, // Office-level routing without named person
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->destOffice->id,
            'receiver_user_id' => null, // Unassigned individual
            'status' => 'Pending',
            'sort_order' => 1,
            'signature_required' => false,
            'scanned_at' => now(),
        ]);

        // Accounting staff member (belongs to destOffice) opening /documents/{id}/workflow
        $response = $this->loginUser($this->destOfficeStaff)
            ->get(route('documents.workflow', $document->id));

        $response->assertStatus(200);
        $response->assertSee('Update State');

        // Accounting staff can execute workflow action
        $postResponse = $this->loginUser($this->destOfficeStaff)
            ->post(route('documents.workflowAction', $document->id), [
                'status' => 'Under Review',
                'notes' => 'Accounting office is now reviewing.',
            ]);

        $postResponse->assertStatus(302);
        $document->refresh();
        $this->assertEquals('Under Review', $document->status);
    }

    /**
     * Test: Unauthorized user gets 403 on view and perform.
     */
    public function test_unauthorized_user_receives_403()
    {
        $document = Document::create([
            'title' => 'Confidential Memo',
            'type' => 'Administrative',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
        ]);

        // Stranger viewing /documents/{id}/workflow should receive 403
        $response = $this->loginUser($this->unauthorizedUser)
            ->get(route('documents.workflow', $document->id));

        $response->assertStatus(403);

        // Stranger attempting action should receive 403
        $postResponse = $this->loginUser($this->unauthorizedUser)
            ->post(route('documents.workflowAction', $document->id), [
                'status' => 'Received',
                'notes' => 'Unauthorized attempt',
            ]);

        $postResponse->assertStatus(403);
    }

    /**
     * Test: Completed and Reverted documents allow authorized participants to view without 403.
     */
    public function test_completed_and_reverted_documents_allow_participants_to_view()
    {
        // 1. Completed Document
        $completedDoc = Document::create([
            'title' => 'Completed Agreement',
            'type' => 'Contract',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->destOfficeStaff->id,
            'status' => 'Completed',
        ]);

        DocumentRouting::create([
            'document_id' => $completedDoc->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->destOfficeStaff->id,
            'status' => 'Completed',
            'sort_order' => 1,
        ]);

        $viewCompleted = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $completedDoc->id));

        $viewCompleted->assertStatus(200);
        $viewCompleted->assertSee('terminal status');

        // 2. Reverted Document
        $revertedDoc = Document::create([
            'title' => 'Reverted Submission',
            'type' => 'Form',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->originOffice->id,
            'receiver_user_id' => $this->creatorUser->id,
            'status' => 'Reverted',
        ]);

        DocumentRouting::create([
            'document_id' => $revertedDoc->id,
            'from_office_id' => $this->destOffice->id,
            'to_office_id' => $this->originOffice->id,
            'receiver_user_id' => $this->creatorUser->id,
            'status' => 'Pending',
            'sort_order' => 2,
        ]);

        $viewReverted = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $revertedDoc->id));

        $viewReverted->assertStatus(200);
    }

    /**
     * Test: On Process documents allow viewing by creator and action by active receiver.
     */
    public function test_on_process_document_workflow()
    {
        $document = Document::create([
            'title' => 'Procurement Request In-Process',
            'type' => 'Procurement',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Under Review', // In process
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => now(),
        ]);

        // Creator can view in view-only mode
        $creatorView = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $document->id));
        $creatorView->assertStatus(200);
        $creatorView->assertSee('Action Restricted (View Only)');

        // Receiver can act
        $receiverView = $this->loginUser($this->receiverUser)
            ->get(route('documents.workflow', $document->id));
        $receiverView->assertStatus(200);
        $receiverView->assertSee('Update State');
    }

    /**
     * Test: For Approval and Approved documents allow participants to view and approver to act.
     */
    public function test_for_approval_and_approved_document_workflow()
    {
        // 1. For Approval Document
        $docForApproval = Document::create([
            'title' => 'Curriculum Revision',
            'type' => 'Academic',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->destOfficeStaff->id,
            'status' => 'For Approval',
        ]);

        DocumentRouting::create([
            'document_id' => $docForApproval->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->destOfficeStaff->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'signature_required' => false,
            'scanned_at' => now(),
        ]);

        // Approver views and can execute approval
        $approverView = $this->loginUser($this->destOfficeStaff)
            ->get(route('documents.workflow', $docForApproval->id));
        $approverView->assertStatus(200);
        $approverView->assertSee('Update State');

        // Execute Approval
        $postApproval = $this->loginUser($this->destOfficeStaff)
            ->post(route('documents.workflowAction', $docForApproval->id), [
                'status' => 'Approved',
                'notes' => 'Curriculum changes approved.',
            ]);
        $postApproval->assertStatus(302);
        $docForApproval->refresh();
        // Since this was the final routing step, approval completes the document
        $this->assertEquals('Completed', $docForApproval->status);

        // 2. Approved/Completed Document can still be viewed by creator without 403
        $creatorView = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $docForApproval->id));
        $creatorView->assertStatus(200);
    }

    /**
     * Test: Missing routing record fallback allows creator to view with action buttons disabled.
     */
    public function test_missing_routing_record_allows_creator_view_with_actions_disabled()
    {
        $docNoRouting = Document::create([
            'title' => 'Orphan Document Without Hops',
            'type' => 'General',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->destOfficeStaff->id,
            'status' => 'Pending',
        ]);

        // No DocumentRouting records exist
        $this->assertEquals(0, DocumentRouting::where('document_id', $docNoRouting->id)->count());

        // Creator views without 403
        $response = $this->loginUser($this->creatorUser)
            ->get(route('documents.workflow', $docNoRouting->id));

        $response->assertStatus(200);
        $response->assertSee('Action Restricted (View Only)');
    }

    /**
     * Test: Admin can view and perform action on any document regardless of assignment.
     */
    public function test_admin_can_view_and_perform_action_on_any_document()
    {
        $document = Document::create([
            'title' => 'Internal HR Audit',
            'type' => 'Administrative',
            'uploaded_by' => $this->creatorUser->id,
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'current_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->transitOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => now(),
        ]);

        // Admin can view
        $adminView = $this->loginUser($this->adminUser)
            ->get(route('documents.workflow', $document->id));
        $adminView->assertStatus(200);
        $adminView->assertSee('Update State');

        // Admin can perform workflow action
        $postResponse = $this->loginUser($this->adminUser)
            ->post(route('documents.workflowAction', $document->id), [
                'status' => 'Under Review',
                'notes' => 'Admin override review.',
            ]);
        $postResponse->assertStatus(302);
        $document->refresh();
        $this->assertEquals('Under Review', $document->status);
    }
}

