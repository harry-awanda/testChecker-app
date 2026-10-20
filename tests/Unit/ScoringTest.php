<?php
namespace Tests\Unit;
use App\Models\{Answer,Question,User};
use App\Services\GradingService;
use Tests\{CreatesSchool,TestCase};
class ScoringTest extends TestCase {
    use CreatesSchool;
    public function test_complex_scoring_handles_penalty_floor_and_no_distractors():void {
        $grader=app(GradingService::class);$teacher=new User;
        $base=$this->objectiveQuestion(['type'=>'complex_choice','weight'=>12,'scoring_mode'=>'partial','options'=>[['key'=>'A','text'=>'A'],['key'=>'B','text'=>'B'],['key'=>'C','text'=>'C'],['key'=>'D','text'=>'D']],'answer_key'=>['A','B']]);
        foreach([[['A'],6],[['A','B'],12],[['A','C'],0],[['C','D'],0],[[],0],[['A','B','C'],6]] as [$selected,$expected]) {
            $result=$grader->grade($teacher,new Question($base),new Answer(['selected_options'=>$selected,'teacher_confirmed'=>true]));
            $this->assertEquals($expected,$result['score']);
        }
        $base['options']=array_slice($base['options'],0,2);
        $this->assertEquals(6,$grader->grade($teacher,new Question($base),new Answer(['selected_options'=>['A'],'teacher_confirmed'=>true]))['score']);
        $base['scoring_mode']='all_or_nothing';
        $this->assertEquals(0,$grader->grade($teacher,new Question($base),new Answer(['selected_options'=>['A'],'teacher_confirmed'=>true]))['score']);
    }
    public function test_true_false_and_single_choice_require_exact_match():void {
        foreach([$this->objectiveQuestion(),$this->objectiveQuestion(['type'=>'true_false','answer_key'=>['false']])] as $data) {
            $result=app(GradingService::class)->grade(new User,new Question($data),new Answer(['selected_options'=>$data['answer_key'],'teacher_confirmed'=>true]));
            $this->assertEquals(10,$result['score']);
        }
    }
}
