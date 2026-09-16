<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\DocumentView;
use App\Models\ActivityLog;

class DocumentViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_view_tracking_rules()
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 11, 41, 0));

        $office = Office::create(['name' => 'Office of the President', 'department' => 'Administration']);
        
        $uploader = User::create([
            'name' => 'Shane Muesco',
            'username' => 'shane_test',
            'email' => 'shane@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'office_id' => $office->id,
        ]);

        $viewer = User::create([
            'name' => 'Another User',
            'username' => 'another_test',
            'email' => 'another@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'office_id' => $office->id,
        ]);

        $document = Document::create([
            'title' => 'Sample3',
            'type' => 'DOCUMENT',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'origin_office_id' => $office->id,
            'current_office_id' => $office->id,
            'destination_office_id' => $office->id,
            'status' => 'Pending',
            'uploaded_by' => $uploader->id,
        ]);

        // Add a routing record so that viewer counts as a routed recipient
        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $office->id,
            'to_office_id' => $office->id,
            'receiver_user_id' => $viewer->id,
            'sender_user_id' => $uploader->id,
            'status' => 'Pending'
        ]);

        $sessionData = [
            'authenticated' => true,
            'user_id' => $viewer->id,
            'user_role' => 'ADMIN',
            'user_name' => $viewer->name,
        ];

        // 1. Initial view log should NOT be created for a normal page load (without notification)
        $response = $this->withSession($sessionData)
            ->get(route('track.detail', $document->id));

        $response->assertStatus(200);
        $this->assertEquals(0, DocumentView::count());
        $this->assertEquals(0, ActivityLog::where('action', 'DOCUMENT VIEWED')->count());

        // 2. View log SHOULD be created when loading page from a notification
        $response = $this->withSession($sessionData)
            ->get(route('track.detail', $document->id) . '?from_notification=1');

        $response->assertStatus(200);
        $this->assertEquals(1, DocumentView::count());
        $this->assertEquals(1, ActivityLog::where('action', 'DOCUMENT VIEWED')->count());
        
        $firstViewedAt = DocumentView::first()->viewed_at;

        // 3. Page refreshes (even with notification param) should preserve the first view timestamp and prevent duplicates
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 11, 42, 30)); // 90s later

        $response = $this->withSession($sessionData)
            ->get(route('track.detail', $document->id) . '?from_notification=1');

        $this->assertEquals(1, DocumentView::count());
        $this->assertEquals(2, ActivityLog::where('action', 'DOCUMENT VIEWED')->count()); // access event is logged, but DocumentView is not duplicated
        $this->assertEquals($firstViewedAt->toDateTimeString(), DocumentView::first()->viewed_at->toDateTimeString());

        // 4. Successful download (open file) should also record view if not already viewed, and log access
        $uploaderSession = [
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name,
        ];
        
        // Setup document file
        Storage::disk('public')->put('test.pdf', 'dummy content');
        $document->update(['file_path' => 'test.pdf']);

        // A different recipient (uploader is not a routed recipient, but let's test a receiver who hasn't viewed yet)
        $viewer2 = User::create([
            'name' => 'Recipient 2',
            'username' => 'rec2',
            'email' => 'rec2@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'office_id' => $office->id,
        ]);

        DocumentRouting::create([
            'document_id' => $document->id,
            'from_office_id' => $office->id,
            'to_office_id' => $office->id,
            'receiver_user_id' => $viewer2->id,
            'sender_user_id' => $uploader->id,
            'status' => 'Pending',
            'scanned_at' => now(),
        ]);

        $sessionData2 = [
            'authenticated' => true,
            'user_id' => $viewer2->id,
            'user_role' => 'ADMIN',
            'user_name' => $viewer2->name,
        ];

        // Recipient 2 downloads document (Open File) - should create view and activity log
        $response = $this->withSession($sessionData2)
            ->get(route('documents.download', $document->id));

        $response->assertStatus(200);
        $this->assertEquals(2, DocumentView::count());
        $this->assertEquals(3, ActivityLog::where('action', 'DOCUMENT VIEWED')->count());

        $this->assertDatabaseHas('document_views', [
            'document_id' => $document->id,
            'user_id' => $viewer2->id,
        ]);

        Carbon::setTestNow(); // Reset test time
    }
}
