<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\ActivityLog;
use App\Models\AuditTrail;

class SignatureDocumentAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_document_acceptance_flow()
    {
        $office = Office::create(['name' => 'Registrar', 'department' => 'Academic']);
        $office2 = Office::create(['name' => 'Academic Dean', 'department' => 'Academic']);

        $uploader = User::create([
            'name' => 'Uploader User',
            'username' => 'uploader_test',
            'email' => 'uploader@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'STAFF',
            'office_id' => $office->id,
        ]);

        $receiver = User::create([
            'name' => 'Shane Muesco',
            'username' => 'shane_test',
            'email' => 'shane@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'STAFF',
            'office_id' => $office2->id,
        ]);

        $document = Document::create([
            'title' => 'Important Document',
            'type' => 'DOCUMENT',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'origin_office_id' => $office->id,
            'current_office_id' => $office->id,
            'destination_office_id' => $office2->id,
            'status' => 'Pending',
            'uploaded_by' => $uploader->id,
        ]);

        $routing = DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $office->id,
            'to_office_id' => $office2->id,
            'receiver_user_id' => $receiver->id,
            'sender_user_id' => $uploader->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'signature_required' => true,
        ]);

        $sessionData = [
            'authenticated' => true,
            'user_id' => $receiver->id,
            'user_role' => 'STAFF',
            'user_name' => $receiver->name,
        ];

        // 1. Trying to accept WITHOUT signature should return a validation or signature error redirect
        $response = $this->withSession($sessionData)
            ->post(route('documents.workflowAction', $document->id), [
                'status' => 'Accepted',
                'notes' => 'Reviewed and accepted.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');

        // 2. Accepting WITH valid drawn signature should succeed
        $response = $this->withSession($sessionData)
            ->post(route('documents.workflowAction', $document->id), [
                'status' => 'Accepted',
                'notes' => 'Reviewed and accepted.',
                'signature_option' => 'draw',
                'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', // 1x1 pixel PNG
            ]);

        $response->assertStatus(302);
        
        // Assertions:
        // - Document status should be Accepted (since it's completed at final destination) OR completed depending on the routing hops.
        // Let's reload document
        $document->refresh();
        $this->assertEquals('Completed', $document->status); // The final hop was completed, so status becomes Completed.
        
        // Timeline entry (ActivityLog) should be logged as 'Document Accepted'
        $this->assertTrue(ActivityLog::where('action', 'Document Accepted')->exists());

        // Audit Trail should have a log entry
        $this->assertTrue(AuditTrail::where('action', 'Document Accepted: ' . $document->title)->exists());
    }
}
