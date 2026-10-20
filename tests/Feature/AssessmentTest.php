<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\QuestionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\{CreatesSchool,TestCase};
class AssessmentTest extends TestCase {
    use RefreshDatabase, CreatesSchool;
    public function test_only_owner_with_classroom_assignment_can_open_assessment(): void {
        $s=$this->school(); $s['assessment']->questions()->create($this->objectiveQuestion());
        $this->withoutVite()->actingAs($s['teacher'])->get('/assessments/'.$s['assessment']->id)->assertOk();
        $other=User::factory()->create(); $s['classroom']->teachers()->attach($other);
        $this->actingAs($other)->get('/assessments/'.$s['assessment']->id)->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'superadmin']))->get('/assessments/'.$s['assessment']->id)->assertForbidden();
        $s['classroom']->teachers()->detach($s['teacher']);
        $this->actingAs($s['teacher'])->get('/assessments/'.$s['assessment']->id)->assertForbidden();
    }
    public function test_question_editor_and_settings_render(): void {
        $s=$this->school();
        $this->withoutVite()->actingAs($s['teacher'])->get('/assessments/'.$s['assessment']->id.'/edit')->assertOk();
        $this->get('/settings/ai')->assertOk()->assertDontSee('test-placeholder-api-key-only');
        $this->get('/assessments')->assertOk();
    }
    public function test_locked_assessment_cannot_be_changed_but_can_be_versioned(): void {
        $s=$this->school(); $s['assessment']->questions()->create($this->objectiveQuestion()); $s['assessment']->update(['locked_at'=>now(),'status'=>'locked']);
        $this->actingAs($s['teacher'])->put('/assessments/'.$s['assessment']->id,['title'=>'Ubah','subject'=>'Ubah','questions_json'=>json_encode([$this->objectiveQuestion()]),'intent'=>'approve'])->assertStatus(409);
        $this->post('/assessments/'.$s['assessment']->id.'/duplicate')->assertRedirect();
        $this->assertDatabaseHas('assessments',['source_assessment_id'=>$s['assessment']->id,'version'=>2,'status'=>'draft','locked_at'=>null]);
    }
    public function test_invalid_rubric_and_keys_are_rejected(): void {
        $validator=app(QuestionValidator::class);
        foreach ([
            $this->objectiveQuestion(['answer_key'=>['Z']]),
            $this->essayQuestion(['weight'=>20]),
            $this->objectiveQuestion(['answer_key'=>['A','B']]),
        ] as $question) {
            try { $validator->validate([$question]); $this->fail('Invalid question accepted.'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
