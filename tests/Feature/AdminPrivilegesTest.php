<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminPrivilegesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_access_offices_and_audit_logs_and_admin_pages()
    {
        $office = Office::create(['name' => 'IT Office', 'department' => 'IT']);

        $superAdmin = User::create([
            'name' => 'Super Admin User',
            'username' => 'superadmin',
            'email' => 'superadmin@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Super Administrator',
            'office_id' => $office->id,
            'needs_password_change' => false,
        ]);

        $sessionData = [
            'authenticated' => true,
            'user_id' => $superAdmin->id,
            'user_role' => 'Super Administrator',
            'user_name' => $superAdmin->name,
        ];

        // 1. Audit Logs (activity.index)
        $response = $this->actingAs($superAdmin)->withSession($sessionData)->get(route('activity.index'));
        $response->assertStatus(200);

        // 2. Offices (offices.index)
        $response = $this->actingAs($superAdmin)->withSession($sessionData)->get(route('offices.index'));
        $response->assertStatus(200);

        // 3. Settings (settings.index)
        $response = $this->actingAs($superAdmin)->withSession($sessionData)->get(route('settings.index'));
        $response->assertStatus(200);

        // 4. Reports (reports.index)
        $response = $this->actingAs($superAdmin)->withSession($sessionData)->get(route('reports.index'));
        $response->assertStatus(200);

        // 5. Users (users.index)
        $response = $this->actingAs($superAdmin)->withSession($sessionData)->get(route('users.index'));
        $response->assertStatus(200);
    }

    public function test_administrator_can_access_offices_and_audit_logs_and_admin_pages()
    {
        $office = Office::create(['name' => 'Admin Office', 'department' => 'Admin']);

        $admin = User::create([
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'adminuser@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Administrator',
            'office_id' => $office->id,
            'needs_password_change' => false,
        ]);

        $sessionData = [
            'authenticated' => true,
            'user_id' => $admin->id,
            'user_role' => 'Administrator',
            'user_name' => $admin->name,
        ];

        // 1. Audit Logs (activity.index)
        $response = $this->actingAs($admin)->withSession($sessionData)->get(route('activity.index'));
        $response->assertStatus(200);

        // 2. Offices (offices.index)
        $response = $this->actingAs($admin)->withSession($sessionData)->get(route('offices.index'));
        $response->assertStatus(200);

        // 3. Settings (settings.index)
        $response = $this->actingAs($admin)->withSession($sessionData)->get(route('settings.index'));
        $response->assertStatus(200);

        // 4. Reports (reports.index)
        $response = $this->actingAs($admin)->withSession($sessionData)->get(route('reports.index'));
        $response->assertStatus(200);

        // 5. Users (users.index)
        $response = $this->actingAs($admin)->withSession($sessionData)->get(route('users.index'));
        $response->assertStatus(200);
    }

    public function test_regular_staff_cannot_access_audit_logs_and_offices()
    {
        $office = Office::create(['name' => 'Staff Office', 'department' => 'Staff']);

        $staff = User::create([
            'name' => 'Staff User',
            'username' => 'staffuser',
            'email' => 'staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Staff',
            'office_id' => $office->id,
            'needs_password_change' => false,
        ]);

        $sessionData = [
            'authenticated' => true,
            'user_id' => $staff->id,
            'user_role' => 'Staff',
            'user_name' => $staff->name,
        ];

        $this->actingAs($staff)->withSession($sessionData)->get(route('activity.index'))->assertStatus(403);
        $this->actingAs($staff)->withSession($sessionData)->get(route('offices.index'))->assertStatus(403);
        $this->actingAs($staff)->withSession($sessionData)->get(route('reports.index'))->assertStatus(403);
    }
}
