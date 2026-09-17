<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $authUser = \Illuminate\Support\Facades\Auth::user();
            $request->session()->put('authenticated', true);
            $request->session()->put('user_id', $authUser->id);
            $request->session()->put('user_role', $authUser->role);
            $request->session()->put('user_name', $authUser->name);
        } elseif ($request->session()->has('user_id')) {
            $dbUser = \App\Models\User::find($request->session()->get('user_id'));
            if ($dbUser) {
                $request->session()->put('user_role', $dbUser->role);
                $request->session()->put('user_name', $dbUser->name);
            }
        }

        if (!$request->session()->get('authenticated', false) || !$request->session()->has('user_id')) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
