<?php
namespace Tests\Feature;
use App\Models\{QuestionGrade,User};
use App\Services\{ReportService,SubmissionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\{CreatesSchool,TestCase};
class GradingTest extends TestCase {
    use RefreshDatabase,CreatesSchool;
    private function prepare(bool $essay=true):array {
        $s=$this->school();$q=$s['assessment']->questions()->create($this->objectiveQuestion());
        if($essay)$s['assessment']->questions()->create($this->essayQuestion());
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $this->actingAs($s['teacher']);
        $answers=$revision->answers()->get()->map(fn($a)=>['question_id'=>$a->question_id,'transcription'=>$a->question_id===$q->id?'A':'Penjelasan siswa.','selected_options'=>$a->question_id===$q->id?['A']:[],'teacher_confirmed'=>true])->all();
        $this->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>0,'answers'=>$answers])->assertOk();
        return [...$s,'revision'=>$revision->fresh(),'question'=>$q];
    }
    private function fakeGrade(float $score):void {
        Http::fake(['*'=>Http::response(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>json_encode(['criteria'=>[['index'=>0,'score'=>$score,'reason'=>'Sebagian kriteria terpenuhi.']],'feedback'=>'Perjelas hubungan antar gagasan.'])]]]]]])]);
    }
    public function test_full_grading_override_finalization_and_revision_keep_effective_score():void {
        $s=$this->prepare();$revision=$s['revision'];$essay=$s['assessment']->questions()->where('type','essay')->first();$this->fakeGrade(6.5);
        $this->postJson('/revisions/'.$revision->id.'/grade/'.$s['question']->id,['edit_version'=>1])->assertOk();
        $this->postJson('/revisions/'.$revision->id.'/grade/'.$essay->id,['edit_version'=>2])->assertOk()->assertJsonPath('revision.status','graded');
        $grade=QuestionGrade::where('question_id',$essay->id)->firstOrFail();
        $this->putJson('/revisions/'.$revision->id.'/grades/'.$grade->id,['edit_version'=>3,'criteria_scores'=>[['index'=>0,'score'=>8]],'reason'=>'Penjelasan relevan dengan rubrik.'])->assertOk();
        $this->assertDatabaseHas('grade_changes',['question_grade_id'=>$grade->id,'teacher_id'=>$s['teacher']->id]);
        $this->postJson('/revisions/'.$revision->id.'/finalize',['edit_version'=>4])->assertOk()->assertJsonPath('revision.final_score',90)->assertJsonPath('revision.status','finalized');
        $this->postJson('/revisions/'.$revision->id.'/finalize',['edit_version'=>4])->assertOk();
        $this->assertDatabaseCount('submission_revisions',1);
        $this->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>5,'answers'=>[]])->assertUnprocessable();
        $draft=app(SubmissionService::class)->revise($revision->submission);
        $this->assertSame(2,$draft->version);$this->assertFalse($draft->answers->first()->teacher_confirmed);
        $rows=app(ReportService::class)->rows($s['assessment']->fresh());
        $this->assertEquals(90,$rows[0]['score']);$this->assertTrue($rows[0]['pending_revision']);
        $this->assertSame(1,$rows[0]['effective_version']);
        $this->withoutVite()->get('/assessments/'.$s['assessment']->id.'/report')->assertOk()->assertSee('90,00');
        Http::assertSentCount(1);
    }
    public function test_missing_confirmation_and_missing_grades_block_finalization():void {
        $s=$this->school();$q=$s['assessment']->questions()->create($this->objectiveQuestion());
        $revision=app(SubmissionService::class)->open($s['assessment'],$s['student']->id);
        $this->actingAs($s['teacher'])->postJson('/revisions/'.$revision->id.'/grade/'.$q->id,['edit_version'=>0])->assertUnprocessable();
        $this->postJson('/revisions/'.$revision->id.'/finalize',['edit_version'=>0])->assertUnprocessable();
        $revision->answers()->update(['teacher_confirmed'=>true]);$revision->update(['status'=>'ready']);
        $this->postJson('/revisions/'.$revision->id.'/finalize',['edit_version'=>0])->assertUnprocessable();
    }
    public function test_invalid_ai_score_never_creates_grade():void {
        $s=$this->prepare();$this->fakeGrade(100);$essay=$s['assessment']->questions()->where('type','essay')->first();
        $this->postJson('/revisions/'.$s['revision']->id.'/grade/'.$essay->id,['edit_version'=>1])->assertUnprocessable();
        $this->assertDatabaseCount('question_grades',0);
    }
    public function test_retry_does_not_duplicate_grade_or_repeat_ai_call():void {
        $s=$this->prepare();$this->fakeGrade(7);$essay=$s['assessment']->questions()->where('type','essay')->first();
        foreach([1,1] as $version)$this->postJson('/revisions/'.$s['revision']->id.'/grade/'.$essay->id,['edit_version'=>$version])->assertOk();
        $this->assertDatabaseCount('question_grades',1);Http::assertSentCount(1);
    }
    public function test_superadmin_cannot_export_or_read_revision():void {
        $s=$this->prepare(false);$this->actingAs(User::factory()->create(['role'=>'superadmin']));
        $this->get('/assessments/'.$s['assessment']->id.'/export/csv')->assertForbidden();
        $this->get('/revisions/'.$s['revision']->id)->assertForbidden();
    }
    public function test_deleting_assessment_cascades_answers_and_grades():void {
        $s=$this->prepare(false);
        $this->postJson('/revisions/'.$s['revision']->id.'/grade/'.$s['question']->id,['edit_version'=>1])->assertOk();
        $this->delete('/assessments/'.$s['assessment']->id,['confirmation'=>'HAPUS'])->assertRedirect('/assessments');
        foreach(['answers','question_grades','submission_revisions','submissions','assessment_roster','questions'] as $table)$this->assertDatabaseCount($table,0);
        $this->assertDatabaseCount('students',1);
    }
    public function test_score_adjustment_audit_survives_reopening_answer_review():void {
        $s=$this->prepare(false);$revision=$s['revision'];
        $this->postJson('/revisions/'.$revision->id.'/grade/'.$s['question']->id,['edit_version'=>1])->assertOk();
        $grade=$revision->grades()->firstOrFail();
        $this->putJson('/revisions/'.$revision->id.'/grades/'.$grade->id,['edit_version'=>2,'score'=>8,'criteria_scores'=>[],'reason'=>'Penyesuaian sesuai rubrik.'])->assertOk();
        $answers=$revision->answers()->get()->map(fn($a)=>['question_id'=>$a->question_id,'transcription'=>'A','selected_options'=>['A'],'teacher_confirmed'=>true])->all();
        $this->putJson('/revisions/'.$revision->id.'/review',['edit_version'=>3,'answers'=>$answers])->assertOk();
        $this->assertDatabaseCount('question_grades',0);
        $this->assertDatabaseHas('grade_changes',['submission_revision_id'=>$revision->id,'question_grade_id'=>null,'question_id'=>$s['question']->id]);
    }
}
