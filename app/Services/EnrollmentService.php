<?php
namespace App\Services;
use App\Models\{Classroom, Enrollment, Student};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService {
    public function enroll(Student $student, Classroom $classroom): Enrollment {
        return DB::transaction(function () use ($student, $classroom) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($student->is_archived || $classroom->is_archived || $classroom->academicYear->is_archived) {
                throw ValidationException::withMessages(['classroom_id' => 'Siswa, kelas, dan tahun ajaran harus aktif.']);
            }
            $current = Enrollment::where('student_id', $student->id)->whereNull('ended_at')
                ->whereHas('classroom', fn ($q) => $q->where('academic_year_id', $classroom->academic_year_id))->get();
            $existing = $current->firstWhere('classroom_id', $classroom->id);
            if ($existing) return $existing;
            foreach ($current as $enrollment) $enrollment->update(['ended_at' => now()]);
            return Enrollment::create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'started_at' => now()]);
        });
    }
}
