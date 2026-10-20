<?php
namespace Tests\Feature;
use App\Models\{Classroom,SubmissionRevision};
use App\Services\{EnrollmentService,SubmissionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Storage};
use Tests\{CreatesSchool,TestCase};
class ReadingTest extends TestCase {
    use RefreshDatabase,CreatesSchool,\Tests\SendsEncryptedPhotos;
    private function image():array { return ['mime_type'=>'image/png','data'=>'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jP1kAAAAASUVORK5CYII=']; }
    private function fakeReading(array $overrides=[]):void {
        $result=array_replace(['page_unclear'=>false,'note'=>'','answers'=>[['question_number'=>1,'transcription'=>'A','selected_options'=>['A'],'uncertain'=>true,'uncertainty_note'=>'Pilihan kurang tegas.','location'=>'Kiri atas']]],$overrides);
        Http::fake(['*'=>function($request)use($result) {
            $text=$request['contents'][0]['parts'][0]['text'];
            $this->assertStringNotContainsString('answer_key',$text);
            $this->assertStringNotContainsString('Siswa Uji',$text);
            $this->assertStringNotContainsString('TEST-01',$text);
            return Http::response(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>json_encode($result)]]]]]]);
        }]);
    }
    public function test_open_is_idempotent_and_freezes_roster():void {
        $s=$this->school();$s['assessment']->questions()->create($this->objectiveQuestion());
        $this->actingAs($s['teacher'])->postJson('/assessments/'.$s['assessment']->id.'/submissions',['student_id'=>$s['student']->id])->assertOk();
        $this->postJson('/assessments/'.$s['assessment']->id.'/submissions',['student_id'=>$s['student']->id])->assertOk();
        $this->assertDatabaseCount('submissions',1);$this->assertDatabaseCount('submission_revisions',1);
        $other=Classroom::create(['name'=>'X B','academic_year_id'=>$s['year']->id]);
        app(EnrollmentService::class)->enroll($s['student'],$other);
        $this->withoutVite()->get('/assessments/'.$s['assessment']->id.'/workspace')->assertOk()->assertSee('Siswa Uji');
        $this->assertDatabaseHas('assessment_roster',['student_id'=>$s['student']->id,'enrollment_id'=>$s['enrollment']->id]);
    }
    public function test_uncertain_ocr_keeps_confirmation_false_and_does_not_store_photo():void {
        $s=$this->school();$q=$s['assessment']->questions()->create($this->objectiveQuestion());$this->fakeReading();
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $before=Storage::allFiles();
        $this->actingAs($s['teacher'])->postPhoto('/revisions/'.$revision->id.'/read',[
            'edit_version'=>0,'page_id'=>'11111111-1111-4111-8111-111111111111','document'=>$this->image(),
        ])->assertOk()->assertJsonPath('revision.answers.0.teacher_confirmed',false)->assertJsonPath('revision.answers.0.is_uncertain',true);
        $this->assertSame($before,Storage::allFiles());
        $answer=$revision->answers()->first();
        $this->assertStringNotContainsString($this->image()['data'],json_encode($answer->toArray()));
        $this->assertDatabaseHas('submission_revisions',['id'=>$revision->id,'status'=>'pending_review']);
        Http::assertSentCount(1);
    }
    public function test_rereading_same_page_replaces_source_instead_of_duplicating():void {
        $s=$this->school();$s['assessment']->questions()->create($this->objectiveQuestion());$this->fakeReading();
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $this->actingAs($s['teacher']);
        foreach([0,1] as $version)$this->postPhoto('/revisions/'.$revision->id.'/read',['edit_version'=>$version,'page_id'=>'11111111-1111-4111-8111-111111111111','document'=>$this->image()])->assertOk();
        $answer=$revision->answers()->first();$this->assertSame('A',$answer->transcription);$this->assertCount(1,$answer->sources);
    }
    public function test_missing_answers_and_stale_updates_are_rejected():void {
        $s=$this->school();$q=$s['assessment']->questions()->create($this->objectiveQuestion());$s['assessment']->questions()->create($this->essayQuestion());
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $this->actingAs($s['teacher'])->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>0,'answers'=>[['question_id'=>$q->id,'transcription'=>'A','selected_options'=>['A'],'teacher_confirmed'=>true]]])->assertUnprocessable();
        $answers=$revision->answers->map(fn($a)=>['question_id'=>$a->question_id,'transcription'=>'','selected_options'=>[],'teacher_confirmed'=>true])->all();
        $this->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>0,'answers'=>$answers])->assertOk()->assertJsonPath('revision.status','ready');
        $this->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>0,'answers'=>$answers])->assertUnprocessable();
    }
    public function test_unknown_ai_question_rejects_entire_reading():void {
        $s=$this->school();$s['assessment']->questions()->create($this->objectiveQuestion());
        $this->fakeReading(['answers'=>[['question_number'=>999,'transcription'=>'?','selected_options'=>[],'uncertain'=>true,'uncertainty_note'=>'','location'=>'']]]);
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $this->actingAs($s['teacher'])->postPhoto('/revisions/'.$revision->id.'/read',['edit_version'=>0,'page_id'=>'11111111-1111-4111-8111-111111111111','document'=>$this->image()])->assertUnprocessable();
        $this->assertDatabaseHas('submission_revisions',['id'=>$revision->id,'edit_version'=>0]);
    }
    public function test_same_option_can_be_selected_on_different_questions():void {
        $s=$this->school();$first=$s['assessment']->questions()->create($this->objectiveQuestion());
        $second=$s['assessment']->questions()->create($this->objectiveQuestion(['number'=>2]));
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $response=$this->actingAs($s['teacher'])->postJson('/assessments/'.$s['assessment']->id.'/submissions',['student_id'=>$s['student']->id])->assertOk();
        $this->assertSame(0,$response->json('revision.edit_version'));
        $answers=[$first,$second];
        $payload=array_map(fn($q)=>['question_id'=>$q->id,'transcription'=>'A','selected_options'=>['A'],'teacher_confirmed'=>true],$answers);
        $this->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>0,'answers'=>$payload])->assertOk()->assertJsonPath('revision.status','ready');
    }
}
