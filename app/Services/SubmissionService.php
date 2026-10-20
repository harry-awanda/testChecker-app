<?php
namespace App\Services;
use App\Models\{Assessment,Enrollment,Submission,SubmissionRevision};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SubmissionService {
    public function lockRoster(Assessment $assessment): void {
        DB::transaction(function() use($assessment) {
            $locked=Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if($locked->locked_at) return;
            if(!$locked->approved_at || !$locked->questions()->exists()) $this->fail('Setujui soal dan rubrik sebelum mulai koreksi.');
            if($locked->classroom->is_archived || $locked->classroom->academicYear->is_archived) $this->fail('Kelas atau tahun ajaran sudah diarsipkan.');
            $enrollments=Enrollment::with('student')->where('classroom_id',$locked->classroom_id)->whereNull('ended_at')->whereHas('student',fn($q)=>$q->where('is_archived',false))->get();
            if($enrollments->isEmpty()) $this->fail('Belum ada siswa aktif dalam kelas.');
            DB::table('assessment_roster')->insert($enrollments->map(fn($e)=>[
                'assessment_id'=>$locked->id,'student_id'=>$e->student_id,'enrollment_id'=>$e->id,
                'student_name'=>$e->student->name,'student_code'=>$e->student->student_code,
            ])->all());
            $locked->update(['status'=>'locked','locked_at'=>now()]);
        });
    }
    public function open(Assessment $assessment,int $studentId): SubmissionRevision {
        $this->lockRoster($assessment);
        return DB::transaction(function() use($assessment,$studentId) {
            Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $entry=DB::table('assessment_roster')->where('assessment_id',$assessment->id)->where('student_id',$studentId)->first();
            if(!$entry) $this->fail('Siswa tidak termasuk daftar peserta tes ini.');
            $submission=Submission::firstOrCreate(['assessment_id'=>$assessment->id,'student_id'=>$studentId],['enrollment_id'=>$entry->enrollment_id]);
            $revision=$submission->revisions()->latest('version')->first();
            return $revision ?? $this->createRevision($submission,1);
        });
    }
    public function revise(Submission $submission): SubmissionRevision {
        return DB::transaction(function() use($submission) {
            Submission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $latest=$submission->revisions()->latest('version')->firstOrFail();
            if($latest->status!=='finalized') return $latest;
            return $this->createRevision($submission,$latest->version+1,$latest);
        });
    }
    private function createRevision(Submission $submission,int $version,?SubmissionRevision $from=null): SubmissionRevision {
        $revision=$submission->revisions()->create(['version'=>$version,'status'=>'pending_review','page_ids'=>[]]);
        foreach($submission->assessment->questions as $question) {
            $old=$from?->answers()->where('question_id',$question->id)->first();
            $revision->answers()->create([
                'question_id'=>$question->id,'transcription'=>$old?->transcription ?? '',
                'selected_options'=>$old?->selected_options ?? [],'sources'=>[],
                'is_uncertain'=>$old?->is_uncertain ?? true,'uncertainty_note'=>$old?->uncertainty_note ?? 'Belum dibaca. Konfirmasikan jawaban, termasuk jika kosong.',
                'teacher_confirmed'=>false,
            ]);
        }
        return $revision;
    }
    public function editable(SubmissionRevision $revision,int $editVersion): void {
        if($revision->status==='finalized') $this->fail('Nilai sudah disahkan. Buat revisi untuk mengubahnya.');
        if($revision->edit_version!==$editVersion) $this->fail('Data telah berubah di tab atau permintaan lain. Muat kembali hasil tersimpan sebelum melanjutkan.');
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['submission'=>$message]); }
}
