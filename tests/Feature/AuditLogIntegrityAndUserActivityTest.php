<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditLogIntegrityAndUserActivityTest extends TestCase
{
    use RefreshDatabase;

    protected $adminOffice;
    protected $hrOffice;
    protected $accountingOffice;

    protected $adminUser;
    protected $userA;
    protected $userB;
    protected $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminOffice = Office::create(['name' => 'Office of the President', 'department' => 'Administration']);
        $this->hrOffice = Office::create(['name' => 'Human Resources (HR)', 'department' => 'Administration']);
        $this->accountingOffice = Office::create(['name' => 'Accounting Office', 'department' => 'Administration']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Administrator',
            'office_id' => $this->adminOffice->id,
            'needs_password_change' => false,
        ]);

        $this->userA = User::create([
            'name' => 'Shane Muesco',
            'username' => 'shanemuesco',
            'email' => 'shane@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Office Head',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        $this->doc = Document::create([
            'title' => 'Student Enrollment Application',
            'tracking_number' => 'DOC-TEST-001',
            'qr_code' => 'QR-DOC-001',
            'origin_office_id' => $this->hrOffice->id,
            'destination_office_id' => $this->hrOffice->id,
            'uploaded_by' => $this->userA->id,
            'status' => 'Pending',
        ]);
    }

    /**
     * Test 1: Brand new user account starts with ZERO activity records in My Activity.
     */
    public function test_newly_created_user_starts_with_empty_activity_history(): void
    {
        $newUser = User::create([
            'name' => 'Shaney Muesco',
            'username' => 'shaneymuesco',
            'email' => 'shaney@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Employee',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        $response = $this->actingAs($newUser)
            ->withSession([
                'user_id' => $newUser->id,
                'user_name' => $newUser->name,
                'user_role' => $newUser->role,
                'office_id' => $newUser->office_id,
            ])
            ->get(route('activity.my'));

        $response->assertStatus(200);
        $response->assertSee('No activity logs found matching the selected criteria.');
        $response->assertDontSee('QR Scan Access Denied');
    }

    /**
     * Test 2: Historical activity on a shared office document does NOT leak into newly created user's My Activity.
     */
    public function test_historical_activity_from_other_users_does_not_leak_to_new_user(): void
    {
        // User A performed a denied QR scan on the document in the past
        ActivityLog::create([
            'user_id' => $this->userA->id,
            'user' => $this->userA->name,
            'action' => 'QR Scan Access Denied',
            'document_id' => $this->doc->id,
            'created_at' => now()->subDay(),
        ]);

        // A new user is created in the same office
        $newUser = User::create([
            'name' => 'Shaney Muesco',
            'username' => 'shaneymuesco',
            'email' => 'shaney@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Employee',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        $response = $this->actingAs($newUser)
            ->withSession([
                'user_id' => $newUser->id,
                'user_name' => $newUser->name,
                'user_role' => $newUser->role,
                'office_id' => $newUser->office_id,
            ])
            ->get(route('activity.my'));

        $response->assertStatus(200);
        $response->assertDontSee('QR Scan Access Denied');
        $response->assertDontSee('Shane Muesco');
        $response->assertSee('No activity logs found matching the selected criteria.');
    }

    /**
     * Test 3: When a user genuinely performs an action, it records the exact user_id and appears in My Activity.
     */
    public function test_user_action_records_exact_user_id_and_displays_in_my_activity(): void
    {
        $user = User::create([
            'name' => 'Active Personnel',
            'username' => 'active.user',
            'email' => 'active@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        // Record a genuine action
        ActivityLog::create([
            'user_id' => $user->id,
            'user' => $user->name,
            'action' => 'Document Viewed',
            'document_id' => $this->doc->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'office_id' => $user->office_id,
            ])
            ->get(route('activity.my'));

        $response->assertStatus(200);
        $response->assertSee('Document Viewed');
        $response->assertSee('Active Personnel');
        $response->assertSee($this->doc->title);
    }

    /**
     * Test 4: User A cannot see User B's activity in My Activity.
     */
    public function test_user_isolation_in_my_activity(): void
    {
        $userB = User::create([
            'name' => 'User B Special',
            'username' => 'user.b',
            'email' => 'userb@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'office_id' => $this->accountingOffice->id,
            'needs_password_change' => false,
        ]);

        ActivityLog::create([
            'user_id' => $this->userA->id,
            'user' => $this->userA->name,
            'action' => 'Shane Custom Activity Event',
            'document_id' => $this->doc->id,
        ]);

        ActivityLog::create([
            'user_id' => $userB->id,
            'user' => $userB->name,
            'action' => 'User B Confidential Event',
            'document_id' => null,
        ]);

        // User A visits My Activity
        $resA = $this->actingAs($this->userA)
            ->withSession([
                'user_id' => $this->userA->id,
                'user_name' => $this->userA->name,
                'user_role' => $this->userA->role,
                'office_id' => $this->userA->office_id,
            ])
            ->get(route('activity.my'));

        $resA->assertSee('Shane Custom Activity Event');
        $resA->assertDontSee('User B Confidential Event');
        $resA->assertDontSee('User B Special');

        // User B visits My Activity
        $resB = $this->actingAs($userB)
            ->withSession([
                'user_id' => $userB->id,
                'user_name' => $userB->name,
                'user_role' => $userB->role,
                'office_id' => $userB->office_id,
            ])
            ->get(route('activity.my'));

        $resB->assertSee('User B Confidential Event');
        $resB->assertDontSee('Shane Custom Activity Event');
    }

    /**
     * Test 5: Manipulating query parameters cannot bypass user_id filtering.
     */
    public function test_query_parameter_tampering_cannot_reveal_other_users_activity(): void
    {
        $newUser = User::create([
            'name' => 'Tamper Attempt User',
            'username' => 'tamper.user',
            'email' => 'tamper@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Employee',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        ActivityLog::create([
            'user_id' => $this->userA->id,
            'user' => $this->userA->name,
            'action' => 'Secret Action By Shane',
            'document_id' => $this->doc->id,
        ]);

        $response = $this->actingAs($newUser)
            ->withSession([
                'user_id' => $newUser->id,
                'user_name' => $newUser->name,
                'user_role' => $newUser->role,
                'office_id' => $newUser->office_id,
            ])
            ->get(route('activity.my', [
                'user' => 'Shane Muesco',
                'user_id' => $this->userA->id,
                'search' => 'Secret',
            ]));

        $response->assertStatus(200);
        $response->assertDontSee('Secret Action By Shane');
        $response->assertSee('No activity logs found matching the selected criteria.');
    }

    /**
     * Test 6: Admin can access /activity and view organization-wide activity.
     */
    public function test_admin_can_view_all_logs(): void
    {
        ActivityLog::create([
            'user_id' => $this->userA->id,
            'user' => $this->userA->name,
            'action' => 'Shane Operational Audit',
            'document_id' => $this->doc->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession([
                'user_id' => $this->adminUser->id,
                'user_name' => $this->adminUser->name,
                'user_role' => $this->adminUser->role,
                'office_id' => $this->adminUser->office_id,
            ])
            ->get(route('activity.index'));

        $response->assertStatus(200);
        $response->assertSee('Shane Operational Audit');
        $response->assertSee('Shane Muesco');
    }

    /**
     * Test 7: Non-admin users cannot access /activity and receive 403 Access Restricted.
     */
    public function test_non_admin_cannot_access_admin_activity_logs(): void
    {
        $response = $this->actingAs($this->userA)
            ->withSession([
                'user_id' => $this->userA->id,
                'user_name' => $this->userA->name,
                'user_role' => $this->userA->role,
                'office_id' => $this->userA->office_id,
            ])
            ->get(route('activity.index'));

        $response->assertStatus(403);
        $response->assertSee('Access Restricted');
        $response->assertSee('Administrator privileges are required to access this page.');
    }

    /**
     * Test 8: Non-admin dashboard recent activity only contains their own actions.
     */
    public function test_dashboard_user_recent_activity_is_strictly_user_scoped(): void
    {
        $newUser = User::create([
            'name' => 'Dashboard Fresh User',
            'username' => 'dash.user',
            'email' => 'dash@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'status' => 'Active',
            'is_active' => true,
            'office_id' => $this->hrOffice->id,
            'department_id' => $this->hrOffice->department_id ?? null,
            'needs_password_change' => false,
        ]);

        // User A performs action on a document in the same office
        ActivityLog::create([
            'user_id' => $this->userA->id,
            'user' => $this->userA->name,
            'action' => 'Shane Action On Shared Doc',
            'document_id' => $this->doc->id,
        ]);

        $response = $this->actingAs($newUser)
            ->withSession([
                'authenticated' => true,
                'user_id' => $newUser->id,
                'user_name' => $newUser->name,
                'user_role' => $newUser->role,
                'office_id' => $newUser->office_id,
            ])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Shane Action On Shared Doc');
        $response->assertSee('No recent activity logged for your account.');
    }
}
