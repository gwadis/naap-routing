<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class VerifyPasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $userId = session('user_id');
        if ($userId) {
            $user = User::find($userId);
            if ($user && $user->needs_password_change) {
                $route = $request->route();
                if ($route && !in_array($route->getName(), ['login.password.reset', 'login.password.update', 'logout'])) {
                    return redirect()->route('login.password.reset')->with('error', 'You must change your password to continue.');
                }
            }
        }

        return $next($request);
    }
}
