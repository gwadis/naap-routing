<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccessRestrictedPageTest extends TestCase
{
    use RefreshDatabase;

    protected $office;
    protected $admin;
    protected $officeHead;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Office::create(['name' => 'College of Engineering', 'department' => 'Engineering']);

        $this->admin = User::create([
            'name' => 'System Administrator',
            'username' => 'sysadmin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Administrator',
            'office_id' => $this->office->id,
            'needs_password_change' => false,
        ]);

        $this->officeHead = User::create([
            'name' => 'Dean John Doe',
            'username' => 'johndoe',
            'email' => 'dean@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Office Head',
            'office_id' => $this->office->id,
            'needs_password_change' => false,
        ]);

        $this->staff = User::create([
            'name' => 'Staff Jane',
            'username' => 'jane',
            'email' => 'jane@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Staff',
            'office_id' => $this->office->id,
            'needs_password_change' => false,
        ]);
    }

    public function test_office_head_accessing_admin_activity_receives_professional_403_page()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHead->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHead->name,
        ];

        $response = $this->actingAs($this->officeHead)->withSession($session)->get('/activity');

        $response->assertStatus(403);
        $response->assertSee('NAAP ROUTING');
        $response->assertSee('ENTERPRISE');
        $response->assertSee('Access Restricted');
        $response->assertSee("You don't have permission to access this page.", false);
        $response->assertSee('This page is available only to authorized administrators.');
        $response->assertSee('Go Back');
        $response->assertSee('Return to Dashboard');
        $response->assertSee('bi-shield-lock-fill');
    }

    public function test_staff_accessing_admin_activity_receives_professional_403_page()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->staff->id,
            'user_role' => 'Staff',
            'user_name' => $this->staff->name,
        ];

        $response = $this->actingAs($this->staff)->withSession($session)->get('/activity');

        $response->assertStatus(403);
        $response->assertSee('Access Restricted');
        $response->assertSee("You don't have permission to access this page.", false);
        $response->assertSee('This page is available only to authorized administrators.');
    }

    public function test_admin_accesses_full_activity_logs()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->admin->id,
            'user_role' => 'Administrator',
            'user_name' => $this->admin->name,
        ];

        $response = $this->actingAs($this->admin)->withSession($session)->get('/activity');

        $response->assertStatus(200);
        $response->assertSee('Activity Logs');
        $response->assertDontSee('Access Restricted');
    }

    public function test_non_admin_can_access_my_activity_page()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHead->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHead->name,
        ];

        $response = $this->actingAs($this->officeHead)->withSession($session)->get('/my-activity');

        $response->assertStatus(200);
        $response->assertSee('My Activity');
        $response->assertSee('Your personal operational audit trail and actions.');
    }

    public function test_dashboard_shows_view_my_activity_for_office_head()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHead->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHead->name,
        ];

        $response = $this->actingAs($this->officeHead)->withSession($session)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('View My Activity');
        $response->assertSee(route('activity.my'));
        $response->assertDontSee('View All Logs');
    }

    public function test_dashboard_shows_view_all_logs_for_admin()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->admin->id,
            'user_role' => 'Administrator',
            'user_name' => $this->admin->name,
        ];

        $response = $this->actingAs($this->admin)->withSession($session)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('View All Logs');
        $response->assertSee(route('activity.index'));
    }
}
