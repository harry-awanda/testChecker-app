<?php
namespace App\Services;
use App\Models\{Assessment,User};
class AssessmentAccess {
    public function authorize(User $user, Assessment $assessment): void {
        abort_unless($user->role === 'teacher' && $user->is_active && $assessment->teacher_id === $user->id, 403, 'Akses penilaian tidak diizinkan.');
        abort_unless($user->classrooms()->where('classrooms.id',$assessment->classroom_id)->exists(),403,'Penugasan kelas tidak tersedia.');
    }
}
