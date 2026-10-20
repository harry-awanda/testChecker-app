<?php
namespace Tests;
use App\Models\{AcademicYear,Assessment,Classroom,Student,User};
use App\Services\EnrollmentService;
trait CreatesSchool {
    protected function school(): array {
        $teacher = User::factory()->create(['gemini_api_key'=>'test-placeholder-api-key-only','gemini_model'=>'gemini-test','ai_consent_at'=>now()]);
        $year = AcademicYear::create(['name'=>'2026/2027','starts_on'=>'2026-07-01','ends_on'=>'2027-06-30']);
        $classroom = Classroom::create(['name'=>'X A','academic_year_id'=>$year->id]); $classroom->teachers()->attach($teacher);
        $student = Student::create(['name'=>'Siswa Uji','student_code'=>'TEST-01']);
        $enrollment = app(EnrollmentService::class)->enroll($student,$classroom);
        $assessment = Assessment::create(['teacher_id'=>$teacher->id,'classroom_id'=>$classroom->id,'title'=>'Tes Contoh','subject'=>'Bahasa Indonesia','type'=>'exercise','status'=>'approved','approved_at'=>now()]);
        return compact('teacher','year','classroom','student','enrollment','assessment');
    }
    protected function objectiveQuestion(array $overrides=[]): array {
        return array_replace(['number'=>1,'type'=>'multiple_choice','prompt'=>'Pilih jawaban.','weight'=>10,'options'=>[['key'=>'A','text'=>'Satu'],['key'=>'B','text'=>'Dua']],'answer_key'=>['A'],'reference_answer'=>'','criteria'=>[],'scoring_mode'=>'all_or_nothing'],$overrides);
    }
    protected function essayQuestion(array $overrides=[]): array {
        return array_replace(['number'=>2,'type'=>'essay','prompt'=>'Jelaskan.','weight'=>10,'options'=>[],'answer_key'=>[],'reference_answer'=>'Contoh','criteria'=>[['label'=>'Isi','description'=>'Penjelasan tepat','max_score'=>10]],'scoring_mode'=>'all_or_nothing'],$overrides);
    }
}
