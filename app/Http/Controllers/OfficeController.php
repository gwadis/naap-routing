<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\User;

class OfficeController extends Controller
{
    /**
     * Display the Departments or Offices grid.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        // Get the view type from the URL (default to departments)
        $view = $request->query('view', 'departments');

        if ($view == 'offices') {
            // Fetch all offices with pagination
            $offices = Office::orderBy('name', 'asc')->paginate(12);
        } else {
            // Fetch unique departments by grouping offices
            // Note: If you have a separate Department model, you would query that instead.
            $offices = Office::select('department')
                ->whereNotNull('department')
                ->groupBy('department')
                ->paginate(12);
        }

        return view('offices', compact('offices', 'view'));
    }

    /**
     * Store a real office in the database.
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:offices,name',
            'department' => 'required|string|max:255',
            'head' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive'
        ]);

        Office::create($validated);

        return redirect()->route('offices.index', ['view' => 'offices'])
            ->with('success', 'New office has been registered successfully.');
    }

    /**
     * Update an office
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();

        $office = Office::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:offices,name,' . $id,
            'department' => 'required|string|max:255',
            'head' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive'
        ]);

        $office->update($validated);

        return redirect()->route('offices.index', ['view' => 'offices'])
            ->with('success', 'Office updated successfully.');
    }

    /**
     * Delete an office
     */
    public function destroy($id)
    {
        $this->authorizeAdmin();

        $office = Office::findOrFail($id);
        $office->delete();

        return redirect()->route('offices.index', ['view' => 'offices'])
            ->with('success', 'Office deleted successfully.');
    }

    protected function authorizeAdmin()
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($role);

        if (!$isAdmin) {
            abort(403, 'Administrator privileges are required to access this page.');
        }
    }

    /**
     * Get all offices in a department (API endpoint)
     */
    public function getDepartmentOffices($name)
    {
        $offices = Office::where('department', $name)->get()->toArray();
        return response()->json($offices);
    }

    /**
     * Get all users in a department (API endpoint)
     */
    public function getDepartmentUsers($name)
    {
        $users = User::whereHas('department', function($query) use ($name) {
            $query->where('name', $name);
        })->get(['id', 'name', 'email', 'role'])->toArray();
        
        // If no users found via relationship, try finding by department field
        if (empty($users)) {
            $users = User::where('department', $name)->get(['id', 'name', 'email', 'role'])->toArray();
        }
        
        return response()->json($users);
    }

    /**
     * Rename a department across all offices and users (API endpoint)
     */
    public function renameDepartment(Request $request)
    {
        $this->authorizeAdmin();
        
        $validated = $request->validate([
            'old_name' => 'required|string',
            'new_name' => 'required|string'
        ]);

        try {
            // Update all offices with this department
            Office::where('department', $validated['old_name'])
                ->update(['department' => $validated['new_name']]);
            
            return response()->json(['success' => true, 'message' => 'Department renamed successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get staff members for a specific office
     */
    public function getOfficeStaff($officeId)
    {
        $office = Office::findOrFail($officeId);
        
        $users = User::where('office_id', $officeId)
            ->where('status', 'active')
            ->with('department')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'Staff',
                    'department' => $user->department->name ?? 'N/A',
                ];
            });
        
        return response()->json([
            'office' => $office->name,
            'department' => $office->department,
            'staff' => $users,
            'count' => $users->count()
        ]);
    }

    /**
     * Get staff members for a specific department
     */
    public function getDepartmentStaff($department)
    {
        $users = User::whereHas('office', function ($q) use ($department) {
            $q->where('department', $department);
        })->with('office', 'department')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'Staff',
                    'office' => $user->office->name ?? 'N/A',
                ];
            });
        
        return response()->json([
            'department' => $department,
            'staff' => $users,
            'count' => $users->count()
        ]);
    }
}