<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            OfficeSeeder::class,
        ]);

        $academicAffairs = \App\Models\Department::where('name', 'Academic Affairs')->first();
        $adminDept = \App\Models\Department::where('name', 'Administration')->first();

        // Query offices to link users
        $hrOffice = \App\Models\Office::where('name', 'Human Resources (HR)')->first();
        $vpFinanceOffice = \App\Models\Office::where('name', 'Vice President of Finance')->first();
        $presidentOffice = \App\Models\Office::where('name', 'Office of the President')->first();
        $registrarOffice = \App\Models\Office::where('name', 'Registrar')->first();
        $deansOffice = \App\Models\Office::where('name', 'Office of the Deans')->first();
        $recordsOffice = \App\Models\Office::where('name', 'Records Office')->first();
        $directorsOffice = \App\Models\Office::where('name', 'Campus Directors')->first();
        $budgetOffice = \App\Models\Office::where('name', 'Budget Office')->first();
        $accountingOffice = \App\Models\Office::where('name', 'Accounting Office')->first();
        $researchOffice = \App\Models\Office::where('name', 'Research and Extension Office')->first();
        $chairsOffice = \App\Models\Office::where('name', 'Program Chairs')->first();

        // Create or update the seeded admin user
        $user = User::firstOrCreate([
            'email' => 'admin@naap.org',
        ], [
            'name' => 'Admin User',
            'username' => 'v   admin',
            'role' => 'ADMIN',
            'email_verified_at' => now(),
            'password' => 'password',
            'department_id' => $adminDept ? $adminDept->id : null,
            'office_id' => $presidentOffice ? $presidentOffice->id : null,
        ]);

        if ($user->role !== 'ADMIN' || $user->username !== 'admin' || !$user->office_id) {
            $user->fill([
                'role' => 'ADMIN',
                'username' => 'admin',
                'department_id' => $adminDept ? $adminDept->id : null,
                'office_id' => $presidentOffice ? $presidentOffice->id : null,
            ]);
            $user->save();
        }

        // Create or update the Gladys Marmol admin user
        $gladys = User::firstOrCreate([
            'email' => 'marmolgladys7@gmail.com',
        ], [
            'name' => 'Gladys Marmol',
            'username' => 'gladys.marmol',
            'role' => 'ADMIN',
            'status' => 'Active',
            'email_verified_at' => now(),
            'password' => 'Admin@12345',
            'department_id' => $adminDept ? $adminDept->id : null,
            'office_id' => $presidentOffice ? $presidentOffice->id : null,
        ]);

        if ($gladys->role !== 'ADMIN' || $gladys->username !== 'gladys.marmol' || !$gladys->office_id) {
            $gladys->fill([
                'role' => 'ADMIN',
                'username' => 'gladys.marmol',
                'status' => 'Active',
                'department_id' => $adminDept ? $adminDept->id : null,
                'office_id' => $presidentOffice ? $presidentOffice->id : null,
            ]);
            $gladys->save();
        }

        // Seed VPAA User
        \App\Models\User::firstOrCreate([
            'email' => 'vpaa@naap.org',
        ], [
            'name' => 'VPAA User',
            'username' => 'vpaa',
            'role' => 'USER',
            'department_id' => $academicAffairs ? $academicAffairs->id : null,
            'office_id' => $deansOffice ? $deansOffice->id : null,
            'password' => 'password',
            'email_verified_at' => now(),
            'status' => 'Active'
        ]);

        // Seed Registrar User
        \App\Models\User::firstOrCreate([
            'email' => 'registrar@naap.edu',
        ], [
            'name' => 'Registrar Officer',
            'username' => 'registrar',
            'role' => 'USER',
            'department_id' => $academicAffairs ? $academicAffairs->id : null,
            'office_id' => $registrarOffice ? $registrarOffice->id : null,
            'password' => 'password',
            'email_verified_at' => now(),
            'status' => 'Active'
        ]);

        // Seed Finance User
        \App\Models\User::firstOrCreate([
            'email' => 'finance@naap.edu',
        ], [
            'name' => 'Finance Officer',
            'username' => 'finance',
            'role' => 'USER',
            'department_id' => $adminDept ? $adminDept->id : null,
            'office_id' => $vpFinanceOffice ? $vpFinanceOffice->id : null,
            'password' => 'password',
            'email_verified_at' => now(),
            'status' => 'Active'
        ]);

        // Seed users for all other offices
        $officeUsers = [
            ['name' => 'HR Officer', 'email' => 'hr@naap.edu', 'username' => 'hr.user', 'office' => $hrOffice],
            ['name' => 'Records Officer', 'email' => 'records@naap.edu', 'username' => 'records.user', 'office' => $recordsOffice],
            ['name' => 'Campus Director User', 'email' => 'director@naap.edu', 'username' => 'director.user', 'office' => $directorsOffice],
            ['name' => 'Budget Officer', 'email' => 'budget@naap.edu', 'username' => 'budget.user', 'office' => $budgetOffice],
            ['name' => 'Accounting Officer', 'email' => 'accounting@naap.edu', 'username' => 'accounting.user', 'office' => $accountingOffice],
            ['name' => 'Research Officer', 'email' => 'research@naap.edu', 'username' => 'research.user', 'office' => $researchOffice],
            ['name' => 'Program Chair User', 'email' => 'chairs@naap.edu', 'username' => 'chairs.user', 'office' => $chairsOffice],
        ];

        foreach ($officeUsers as $ou) {
            if ($ou['office']) {
                \App\Models\User::firstOrCreate([
                    'email' => $ou['email'],
                ], [
                    'name' => $ou['name'],
                    'username' => $ou['username'],
                    'role' => 'USER',
                    'department_id' => $ou['office']->department === 'Administration' ? ($adminDept->id ?? null) : ($academicAffairs->id ?? null),
                    'office_id' => $ou['office']->id,
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'status' => 'Active'
                ]);
            }
        }

        $this->call([
            DocumentSeeder::class,
        ]);
    }
}
