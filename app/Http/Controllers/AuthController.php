<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function create() { return view('auth.login'); }
    public function store(Request $request) {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', mb_strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
        if (!Auth::attempt([...$data, 'is_active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai, atau akun tidak aktif.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->route('dashboard');
    }
    public function destroy(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
