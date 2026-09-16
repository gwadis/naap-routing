<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Department;

class UserAccountsAndProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_profile_edit_ignores_restricted_fields()
    {
        // 1. Setup departments, offices and user
        $dept1 = Department::create(['name' => 'HR Dept', 'status' => 'active']);
        $dept2 = Department::create(['name' => 'Academic Dept', 'status' => 'active']);
        
        $office1 = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $office2 = Office::create(['name' => 'VPAA Office', 'department' => 'VPAA']);

        $user = User::create([
            'name' => 'Regular Employee',
            'username' => 'employee_test',
            'email' => 'employee_test@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Employee',
            'department_id' => $dept1->id,
            'office_id' => $office1->id,
            'position' => 'Assistant',
            'employee_id' => 'EMP-1111',
            'needs_password_change' => false
        ]);

        // 2. Perform profile update as regular user trying to modify restricted fields
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $user->id,
            'user_role' => 'Employee',
            'user_name' => $user->name,
            'user_email' => $user->email,
        ])->post(route('profile.update'), [
            'name' => 'Updated Regular Employee',
            'email' => 'employee_test@naap.org',
            'phone' => '09123456789',
            // Restricted fields:
            'department_id' => $dept2->id,
            'office_id' => $office2->id,
            'position' => 'Manager',
            'employee_id' => 'EMP-2222',
        ]);

        $response->assertRedirect();
        
        // 3. Refresh user and assert restricted fields remain unchanged while allowed ones change
        $user->refresh();
        $this->assertEquals('Updated Regular Employee', $user->name);
        $this->assertEquals('09123456789', $user->phone);
        $this->assertEquals($dept1->id, $user->department_id);
        $this->assertEquals($office1->id, $user->office_id);
        $this->assertEquals('Assistant', $user->position);
        $this->assertEquals('EMP-1111', $user->employee_id);
    }

    public function test_admin_user_can_edit_all_fields_via_user_management_crud()
    {
        // 1. Setup departments, offices and admin
        $dept1 = Department::create(['name' => 'HR Dept', 'status' => 'active']);
        $dept2 = Department::create(['name' => 'Academic Dept', 'status' => 'active']);
        
        $office1 = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $office2 = Office::create(['name' => 'VPAA Office', 'department' => 'VPAA']);

        $admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin_test',
            'email' => 'admin_test@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'department_id' => $dept1->id,
            'office_id' => $office1->id,
            'needs_password_change' => false
        ]);

        $targetUser = User::create([
            'name' => 'Target User',
            'username' => 'target_user',
            'email' => 'target_user@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Employee',
            'department_id' => $dept1->id,
            'office_id' => $office1->id,
            'position' => 'Staff Assistant',
            'employee_id' => 'EMP-7777',
            'needs_password_change' => false
        ]);

        // 2. Perform user update via Admin Accounts CRUD
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $admin->id,
            'user_role' => 'ADMIN',
            'user_name' => $admin->name,
            'user_email' => $admin->email,
        ])->put(route('users.update', $targetUser->id), [
            'name' => 'Updated Target User',
            'email' => 'target_user_new@naap.org',
            'employee_id' => 'EMP-8888',
            'position' => 'Head Staff',
            'role' => 'Staff',
            'department_id' => $dept2->id,
            'office_id' => $office2->id,
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('users.index'));

        // 3. Assert all modifications were saved
        $targetUser->refresh();
        $this->assertEquals('Updated Target User', $targetUser->name);
        $this->assertEquals('target_user_new@naap.org', $targetUser->email);
        $this->assertEquals('EMP-8888', $targetUser->employee_id);
        $this->assertEquals('Head Staff', $targetUser->position);
        $this->assertEquals('Staff', $targetUser->role);
        $this->assertEquals($dept2->id, $targetUser->department_id);
        $this->assertEquals($office2->id, $targetUser->office_id);
        $this->assertEquals('inactive', $targetUser->status);
    }
}
