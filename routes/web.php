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

Route::middleware(['auth','role:teacher'])->group(function () {
    Route::get('/settings/ai',[\App\Http\Controllers\AiSettingsController::class,'edit'])->name('ai.edit');
    Route::put('/settings/ai',[\App\Http\Controllers\AiSettingsController::class,'update'])->name('ai.update');
    Route::delete('/settings/ai',[\App\Http\Controllers\AiSettingsController::class,'destroy'])->name('ai.destroy');
    Route::get('/settings/ai/models',[\App\Http\Controllers\AiSettingsController::class,'models'])->middleware('throttle:10,1')->name('ai.models');
    Route::post('/settings/ai/test',[\App\Http\Controllers\AiSettingsController::class,'test'])->middleware('throttle:10,1')->name('ai.test');
    Route::post('/settings/ai/test-image',[\App\Http\Controllers\AiSettingsController::class,'testImage'])->middleware('throttle:10,1')->name('ai.test-image');
    Route::get('/assessments',[\App\Http\Controllers\AssessmentController::class,'index'])->name('assessments.index');
    Route::post('/assessments',[\App\Http\Controllers\AssessmentController::class,'store'])->name('assessments.store');
    Route::get('/assessments/{assessment}',[\App\Http\Controllers\AssessmentController::class,'show'])->name('assessments.show');
    Route::get('/assessments/{assessment}/edit',[\App\Http\Controllers\AssessmentController::class,'edit'])->name('assessments.edit');
    Route::put('/assessments/{assessment}',[\App\Http\Controllers\AssessmentController::class,'update'])->name('assessments.update');
    Route::delete('/assessments/{assessment}',[\App\Http\Controllers\AssessmentController::class,'destroy'])->name('assessments.destroy');
    Route::post('/assessments/{assessment}/duplicate',[\App\Http\Controllers\AssessmentController::class,'duplicate'])->name('assessments.duplicate');
    Route::post('/assessments/{assessment}/draft',[\App\Http\Controllers\AssessmentController::class,'draft'])->middleware('throttle:10,1')->name('assessments.draft');
});

Route::middleware(['auth','role:teacher'])->group(function () {
    Route::get('/assessments/{assessment}/workspace',[\App\Http\Controllers\WorkspaceController::class,'show'])->name('workspace.show');
    Route::post('/assessments/{assessment}/submissions',[\App\Http\Controllers\WorkspaceController::class,'open'])->name('submissions.open');
    Route::get('/revisions/{revision}',[\App\Http\Controllers\WorkspaceController::class,'showRevision'])->name('revisions.show');
    Route::post('/submissions/{submission}/revise',[\App\Http\Controllers\WorkspaceController::class,'revise'])->name('submissions.revise');
    Route::post('/revisions/{revision}/read',[\App\Http\Controllers\WorkspaceController::class,'read'])->middleware('throttle:40,1')->name('revisions.read');
    Route::put('/revisions/{revision}/review',[\App\Http\Controllers\WorkspaceController::class,'review'])->name('revisions.review');
});
