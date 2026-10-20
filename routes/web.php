<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/dashboard', function () {
    abort_unless(auth()->user()->is_active, 403);
    return view('dashboard');
})->middleware('auth')->name('dashboard');
