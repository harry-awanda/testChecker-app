<?php
namespace App\Http\Controllers;
use App\Models\{Question,QuestionGrade,SubmissionRevision};
use App\Services\{AssessmentAccess,GradingService,SubmissionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class GradingController extends Controller {
    private function authorize(Request $request,SubmissionRevision $revision,AssessmentAccess $access):void {
        $access->authorize($request->user(),$revision->submission->assessment);
    }
    private function ready(SubmissionRevision $revision):void {
        if($revision->answers()->where('teacher_confirmed',false)->exists() || !in_array($revision->status,['ready','graded'],true)) $this->fail('Konfirmasikan seluruh jawaban dan simpan tinjauan sebelum penilaian.');
    }
    public function grade(Request $request,SubmissionRevision $revision,Question $question,AssessmentAccess $access,SubmissionService $submissions,GradingService $grader) {
        $this->authorize($request,$revision,$access);
        abort_unless($question->assessment_id===$revision->submission->assessment_id,404);
        $data=$request->validate(['edit_version'=>'required|integer|min:0']);
        if($revision->grades()->where('question_id',$question->id)->exists()) return response()->json(app(WorkspaceController::class)->state($revision));
        $submissions->editable($revision,(int)$data['edit_version']);$this->ready($revision);
        $result=$grader->grade($request->user(),$question,$revision->answers()->where('question_id',$question->id)->firstOrFail());
        DB::transaction(function() use($revision,$question,$data,$result,$submissions) {
            $locked=SubmissionRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if($locked->grades()->where('question_id',$question->id)->exists())return;
            $submissions->editable($locked,(int)$data['edit_version']);$this->ready($locked);
            $locked->grades()->create([...$result,'question_id'=>$question->id]);
            $complete=$locked->grades()->count()===$locked->submission->assessment->questions()->count();
            $locked->update(['status'=>$complete?'graded':'ready','edit_version'=>$locked->edit_version+1]);
        });
        return response()->json(app(WorkspaceController::class)->state($revision->fresh()));
    }
    public function override(Request $request,SubmissionRevision $revision,QuestionGrade $grade,AssessmentAccess $access,SubmissionService $submissions) {
        $this->authorize($request,$revision,$access);
        abort_unless($grade->submission_revision_id===$revision->id,404);
        $question=$revision->submission->assessment->questions()->findOrFail($grade->question_id);
        $data=$request->validate([
            'edit_version'=>'required|integer|min:0','score'=>'nullable|numeric|min:0|max:'.$question->weight,
            'reason'=>'required|string|min:3|max:5000','criteria_scores'=>'present|array|max:20','criteria_scores.*.index'=>'required|integer|min:0|distinct','criteria_scores.*.score'=>'required|numeric|min:0',
        ]);
        if($question->isObjective()) {
            if(!isset($data['score']) || $data['criteria_scores'])$this->fail('Masukkan satu skor untuk soal objektif.');
            $newScore=round((float)$data['score'],4);$criteria=[];
        } else {
            if(count($data['criteria_scores'])!==count($question->criteria))$this->fail('Isi skor seluruh kriteria.');
            $criteria=[];$newScore=0;
            foreach($question->criteria as $index=>$criterion) {
                $item=collect($data['criteria_scores'])->first(fn($c)=>(int)$c['index']===$index);
                if(!$item || !is_finite((float)$item['score']) || (float)$item['score']>(float)$criterion['max_score'])$this->fail('Skor kriteria melebihi batas rubrik.');
                $score=round((float)$item['score'],4);$newScore+=$score;
                $criteria[]=['label'=>$criterion['label'],'score'=>$score,'max_score'=>(float)$criterion['max_score'],'reason'=>$data['reason']];
            }
        }
        if($newScore>$question->weight+0.00001)$this->fail('Total skor melebihi bobot soal.');
        DB::transaction(function()use($revision,$grade,$data,$submissions,$newScore,$criteria,$request) {
            $locked=SubmissionRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();$submissions->editable($locked,(int)$data['edit_version']);$this->ready($locked);
            $current=QuestionGrade::whereKey($grade->id)->lockForUpdate()->firstOrFail();
            $before=$current->only(['score','criteria_scores','feedback','source','model_name']);
            $after=['score'=>round($newScore,4),'criteria_scores'=>$criteria,'feedback'=>$data['reason'],'source'=>'teacher','model_name'=>$current->model_name];
            $current->update($after);
            $current->changes()->create(['submission_revision_id'=>$locked->id,'question_id'=>$current->question_id,'teacher_id'=>$request->user()->id,'before'=>$before,'after'=>$after,'reason'=>$data['reason'],'created_at'=>now()]);
            $locked->increment('edit_version');
        });
        return response()->json(app(WorkspaceController::class)->state($revision->fresh()));
    }
    public function finalize(Request $request,SubmissionRevision $revision,AssessmentAccess $access,SubmissionService $submissions) {
        $this->authorize($request,$revision,$access);$data=$request->validate(['edit_version'=>'required|integer|min:0']);
        DB::transaction(function()use($revision,$data,$submissions) {
            $locked=SubmissionRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if($locked->status==='finalized')return;
            $submissions->editable($locked,(int)$data['edit_version']);$this->ready($locked);
            $questions=$locked->submission->assessment->questions;$grades=$locked->grades;
            if($questions->isEmpty() || $grades->count()!==$questions->count())$this->fail('Seluruh soal harus dinilai sebelum pengesahan.');
            foreach($questions as $question) {
                $grade=$grades->firstWhere('question_id',$question->id);
                if(!$grade || $grade->score<0 || $grade->score>$question->weight+0.00001)$this->fail('Rincian skor tidak valid.');
            }
            $weight=$questions->sum('weight');if($weight<=0)$this->fail('Total bobot harus positif.');
            $score=round(min(100,max(0,$grades->sum('score')/$weight*100)),2);
            $locked->update(['status'=>'finalized','final_score'=>$score,'finalized_at'=>now(),'edit_version'=>$locked->edit_version+1]);
        });
        return response()->json(app(WorkspaceController::class)->state($revision->fresh()));
    }
    private function fail(string $message):never {throw ValidationException::withMessages(['grading'=>$message]);}
}
