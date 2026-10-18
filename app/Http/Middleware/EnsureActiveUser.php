<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Akun tidak aktif. Hubungi administrator.');
        }
        return $next($request);
    }
}
