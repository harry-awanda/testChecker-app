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

Route::middleware(['auth','role:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AdminController::class,'index'])->name('index');
    Route::post('/teachers', [\App\Http\Controllers\AdminController::class,'teacher'])->name('teachers.store');
    Route::put('/teachers/{teacher}', [\App\Http\Controllers\AdminController::class,'teacher'])->name('teachers.update');
    Route::post('/years', [\App\Http\Controllers\AdminController::class,'year'])->name('years.store');
    Route::put('/years/{academicYear}', [\App\Http\Controllers\AdminController::class,'year'])->name('years.update');
    Route::post('/classrooms', [\App\Http\Controllers\AdminController::class,'classroom'])->name('classrooms.store');
    Route::put('/classrooms/{classroom}', [\App\Http\Controllers\AdminController::class,'classroom'])->name('classrooms.update');
    Route::post('/students/import', [\App\Http\Controllers\AdminController::class,'import'])->name('students.import');
    Route::post('/students', [\App\Http\Controllers\AdminController::class,'student'])->name('students.store');
    Route::put('/students/{student}', [\App\Http\Controllers\AdminController::class,'student'])->name('students.update');
});
