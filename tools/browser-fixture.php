<?php
// Only run through npm run test:browser against the isolated browser.sqlite file.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=config('database.connections.sqlite.database');
$expected=realpath(__DIR__.'/../storage/framework/testing').DIRECTORY_SEPARATOR.'browser.sqlite';
if(!app()->environment('testing') || config('database.default')!=='sqlite' || $db!==$expected || !getenv('TEST_PASSWORD')) {
    fwrite(STDERR,"Refusing to initialize a non-isolated test database.\n");exit(1);
}
touch($db);
Illuminate\Support\Facades\Artisan::call('migrate:fresh',['--force'=>true]);
use App\Models\{AcademicYear,Assessment,Classroom,Student,User};
$admin=User::create(['name'=>'Admin Pengujian','email'=>'admin@testchecker.invalid','password'=>getenv('TEST_PASSWORD'),'role'=>'superadmin','is_active'=>true]);
$teacher=User::create(['name'=>'Guru Pengujian','email'=>'teacher@testchecker.invalid','password'=>getenv('TEST_PASSWORD'),'role'=>'teacher','is_active'=>true,'gemini_api_key'=>'browser-test-placeholder-only','gemini_model'=>'gemini-test','ai_consent_at'=>now()]);
$year=AcademicYear::create(['name'=>'2026/2027','starts_on'=>'2026-07-01','ends_on'=>'2027-06-30']);
$classroom=Classroom::create(['academic_year_id'=>$year->id,'name'=>'X A']);$classroom->teachers()->attach($teacher);
foreach(['Siswa Contoh A','Siswa Contoh B'] as $index=>$name) {
    $student=Student::create(['student_code'=>'TEST-'.($index+1),'name'=>$name]);
    app(App\Services\EnrollmentService::class)->enroll($student,$classroom);
}
$assessment=Assessment::create(['teacher_id'=>$teacher->id,'classroom_id'=>$classroom->id,'title'=>'Latihan Pemahaman','subject'=>'Bahasa Indonesia','type'=>'exercise','status'=>'approved','approved_at'=>now()]);
$assessment->questions()->create(['number'=>1,'type'=>'multiple_choice','prompt'=>'Pilih gagasan utama yang paling tepat.','weight'=>10,'options'=>[['key'=>'A','text'=>'Gagasan utama'],['key'=>'B','text'=>'Kalimat tambahan']],'answer_key'=>['A'],'reference_answer'=>'','criteria'=>[],'scoring_mode'=>'all_or_nothing']);
$assessment->questions()->create(['number'=>2,'type'=>'essay','prompt'=>'Jelaskan alasan pilihan Anda.','weight'=>10,'options'=>[],'answer_key'=>[],'reference_answer'=>'Penjelasan relevan.','criteria'=>[['label'=>'Ketepatan alasan','description'=>'Alasan sesuai gagasan utama.','max_score'=>10]],'scoring_mode'=>'all_or_nothing']);
echo "Isolated browser fixtures ready; no production accounts were changed.\n";
