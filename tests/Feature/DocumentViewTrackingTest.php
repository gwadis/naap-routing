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

        // 1. Initial view log should NOT be created for a normal page load
        $response = $this->withSession($sessionData)
            ->get(route('track.detail', $document->id));

        $response->assertStatus(200);
        $this->assertEquals(0, DocumentView::count());
        $this->assertEquals(0, ActivityLog::where('action', 'Document Viewed')->count());

        // 2. Page load even with from_notification must NOT mark document as viewed (Rule 3)
        $response = $this->withSession($sessionData)
            ->get(route('track.detail', $document->id) . '?from_notification=1');

        $response->assertStatus(200);
        $this->assertEquals(0, DocumentView::count());
        $this->assertEquals(0, ActivityLog::where('action', 'Document Viewed')->count());

        // 3. Official Viewed event occurs ONLY upon successful QR scan verification
        $scanResponse = $this->withSession($sessionData)
            ->post(route('qr.scan'), [
                'qr_data' => $document->qr_code ?? (string) $document->id,
                'target_document_id' => $document->id,
            ]);
        $scanResponse->assertStatus(200);
        $scanResponse->assertJson(['success' => true]);

        $this->assertEquals(1, DocumentView::count());
        $this->assertEquals(1, ActivityLog::where('action', 'Document Viewed')->count());
        
        $firstViewedAt = DocumentView::first()->viewed_at;

        // 4. Repeated QR scan preserves original first-view timestamp and does not duplicate DocumentView
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 11, 42, 30)); // 90s later

        $scanResponse2 = $this->withSession($sessionData)
            ->post(route('qr.scan'), [
                'qr_data' => $document->qr_code ?? (string) $document->id,
                'target_document_id' => $document->id,
            ]);
        $scanResponse2->assertStatus(200);

        $this->assertEquals(1, DocumentView::count());
        $this->assertEquals(1, ActivityLog::where('action', 'Document Viewed')->count());
        $this->assertEquals(1, ActivityLog::where('action', 'QR Verified Again')->count());
        $this->assertEquals($firstViewedAt->toDateTimeString(), DocumentView::first()->viewed_at->toDateTimeString());

        // 5. Download file authorization: requires QR scan
        Storage::disk('public')->put('test.pdf', 'dummy content');
        $document->update(['file_path' => 'test.pdf']);

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
            'scanned_at' => null,
        ]);

        $sessionData2 = [
            'authenticated' => true,
            'user_id' => $viewer2->id,
            'user_role' => 'ADMIN',
            'user_name' => $viewer2->name,
        ];

        // Recipient 2 attempts download before QR scan -> 403 Forbidden
        $this->flushSession();
        $response = $this->actingAs($viewer2)->withSession($sessionData2)
            ->get(route('documents.download', $document->id));
        $response->assertStatus(403);

        // Recipient 2 scans QR
        $viewer2Scan = $this->actingAs($viewer2)->withSession($sessionData2)
            ->post(route('qr.scan'), [
                'qr_data' => $document->qr_code ?? (string) $document->id,
                'target_document_id' => $document->id,
            ]);
        $viewer2Scan->assertStatus(200);

        // Now download succeeds
        $sessionData2Verified = array_merge($sessionData2, [
            'qr_verified_' . $document->id => true,
        ]);
        $dlResponse = $this->actingAs($viewer2)->withSession($sessionData2Verified)
            ->get(route('documents.download', $document->id));
        $dlResponse->assertStatus(200);

        $this->assertEquals(2, DocumentView::count());
        $this->assertDatabaseHas('document_views', [
            'document_id' => $document->id,
            'user_id' => $viewer2->id,
        ]);

        Carbon::setTestNow(); // Reset test time
    }
}
