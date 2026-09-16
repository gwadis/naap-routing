<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Administration Department Offices
        Office::firstOrCreate(['name' => 'Human Resources (HR)'], ['department' => 'Administration']);
        Office::firstOrCreate(['name' => 'Vice President of Finance'], ['department' => 'Administration']);
        Office::firstOrCreate(['name' => 'Office of the President'], ['department' => 'Administration']);
        Office::firstOrCreate(['name' => 'Records Office'], ['department' => 'Administration']);
        Office::firstOrCreate(['name' => 'Campus Directors'], ['department' => 'Administration']);
        Office::firstOrCreate(['name' => 'Budget Office'], ['department' => 'Administration']);
        Office::firstOrCreate(['name' => 'Accounting Office'], ['department' => 'Administration']);

        // Academic Affairs Department Offices
        Office::firstOrCreate(['name' => 'Registrar'], ['department' => 'Academic Affairs']);
        Office::firstOrCreate(['name' => 'Office of the Deans'], ['department' => 'Academic Affairs']);
        Office::firstOrCreate(['name' => 'Research and Extension Office'], ['department' => 'Academic Affairs']);
        Office::firstOrCreate(['name' => 'Program Chairs'], ['department' => 'Academic Affairs']);
    }
}
