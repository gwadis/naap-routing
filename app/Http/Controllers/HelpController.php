<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class HelpController extends Controller
{
    /**
     * Display the Help & User Manual page.
     * Automatically adjusts manual content based on the authenticated user's role.
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user() ?? User::find(session('user_id'));
        $role = session('user_role') ?? $currentUser?->role;
        $isAdmin = ($currentUser && $currentUser->isAdmin()) || User::isRoleAdmin($role);

        // For administrators, allow toggling between Admin Manual and Staff Manual view if requested
        $requestedView = $request->query('view');
        if ($isAdmin && $requestedView === 'staff') {
            $manualType = 'staff';
        } elseif ($isAdmin) {
            $manualType = 'admin';
        } else {
            $manualType = 'staff';
        }

        $searchQuery = trim((string) $request->query('search', ''));
        $activeSection = trim((string) $request->query('section', ''));

        return view('help.index', compact(
            'currentUser',
            'role',
            'isAdmin',
            'manualType',
            'searchQuery',
            'activeSection'
        ));
    }
}
