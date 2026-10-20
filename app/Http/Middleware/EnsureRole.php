<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureRole {
    public function handle(Request $request, Closure $next, string $role) {
        if (!$request->user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Akun tidak aktif.');
        }
        abort_unless($request->user()->role === $role, 403, 'Akses tidak diizinkan.');
        return $next($request);
    }
}
