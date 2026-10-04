<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $currentUser = null;
        if (\Illuminate\Support\Facades\Auth::check()) {
            $currentUser = \Illuminate\Support\Facades\Auth::user();
            $request->session()->put('authenticated', true);
            $request->session()->put('user_id', $currentUser->id);
            $request->session()->put('user_role', $currentUser->role);
            $request->session()->put('user_name', $currentUser->name);
        } elseif ($request->session()->has('user_id')) {
            $currentUser = \App\Models\User::find($request->session()->get('user_id'));
            if ($currentUser) {
                \Illuminate\Support\Facades\Auth::login($currentUser);
                $request->session()->put('authenticated', true);
                $request->session()->put('user_role', $currentUser->role);
                $request->session()->put('user_name', $currentUser->name);
            }
        }

        if (!$request->session()->get('authenticated', false) || !$request->session()->has('user_id') || !$currentUser) {
            $request->session()->put('url.intended', $request->fullUrl());
            return redirect()->route('home');
        }

        if (strtolower((string) $currentUser->status) === 'inactive') {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->flush();
            return redirect()->route('home')->with('error', 'Your account has been deactivated. Please contact your administrator.');
        }

        return $next($request);
    }
}
