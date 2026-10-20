<?php
namespace App\Http\Controllers;
use App\Models\{Assessment,Submission,SubmissionRevision};
use App\Services\{AssessmentAccess,SubmissionService};
use App\Services\Ai\{DocumentInput,ReadingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class WorkspaceController extends Controller {
    private function authorizeRevision(Request $request,SubmissionRevision $revision,AssessmentAccess $access):Assessment {
        $assessment=$revision->submission->assessment; $access->authorize($request->user(),$assessment); return $assessment;
    }
    public function show(Request $request,Assessment $assessment,AssessmentAccess $access) {
        $access->authorize($request->user(),$assessment);
        abort_unless($assessment->approved_at,409,'Setujui soal dan rubrik terlebih dahulu.');
        $roster=$assessment->locked_at
            ? DB::table('assessment_roster')->where('assessment_id',$assessment->id)->orderBy('student_name')->get(['student_id','student_name','student_code'])
            : $assessment->classroom->enrollments()->whereNull('ended_at')->whereHas('student',fn($q)=>$q->where('is_archived',false))->with('student')->get()->map(fn($e)=>['student_id'=>$e->student_id,'student_name'=>$e->student->name,'student_code'=>$e->student->student_code]);
        return view('teacher.workspace',['assessment'=>$assessment->load('questions'),'roster'=>$roster]);
    }
    public function open(Request $request,Assessment $assessment,AssessmentAccess $access,SubmissionService $service) {
        $access->authorize($request->user(),$assessment); $data=$request->validate(['student_id'=>'required|integer']);
        return response()->json($this->state($service->open($assessment,(int)$data['student_id'])));
    }
    public function showRevision(Request $request,SubmissionRevision $revision,AssessmentAccess $access) {
        $this->authorizeRevision($request,$revision,$access); return response()->json($this->state($revision));
    }
    public function revise(Request $request,Submission $submission,AssessmentAccess $access,SubmissionService $service) {
        $access->authorize($request->user(),$submission->assessment);
        return response()->json($this->state($service->revise($submission)));
    }
    public function read(Request $request,SubmissionRevision $revision,AssessmentAccess $access,SubmissionService $service,DocumentInput $input,ReadingService $reader) {
        $assessment=$this->authorizeRevision($request,$revision,$access);
        $data=$request->validate(['edit_version'=>'required|integer|min:0','page_id'=>'required|uuid','document'=>'required|array']);
        $service->editable($revision,(int)$data['edit_version']);
        if(!in_array($data['page_id'],$revision->page_ids,true)&&count($revision->page_ids)>=10) throw ValidationException::withMessages(['pages'=>'Maksimal 10 halaman per revisi.']);
        $reading=$reader->read($request->user(),$assessment,$input->part($data['document']));
        DB::transaction(function() use($revision,$data,$reading,$service,$request) {
            $locked=SubmissionRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $service->editable($locked,(int)$data['edit_version']);
            $pageIds=$locked->page_ids;
            if(!in_array($data['page_id'],$pageIds,true)) {
                if(count($pageIds)>=10) throw ValidationException::withMessages(['pages'=>'Maksimal 10 halaman per revisi.']);
                $pageIds[]=$data['page_id'];
            }
            foreach($locked->answers()->with('question')->get() as $answer) {
                $sources=$answer->sources; unset($sources[$data['page_id']]);
                $item=collect($reading['answers'])->firstWhere('question_number',$answer->question->number);
                if($item) $sources[$data['page_id']]=[
                    'text'=>$item['transcription'],'selected_options'=>$item['selected_options'],
                    'uncertain'=>$item['uncertain']||$reading['page_unclear'],'note'=>$item['uncertainty_note'],'location'=>$item['location'],
                ];
                $selected=array_values(array_unique(array_merge(...array_map(fn($s)=>$s['selected_options'],array_values($sources)))));
                $uncertain=!$sources || collect($sources)->contains(fn($s)=>$s['uncertain']);
                if(in_array($answer->question->type,['multiple_choice','true_false'],true)&&count($selected)>1) $uncertain=true;
                $answer->update([
                    'sources'=>$sources,'transcription'=>implode("\n\n",array_column(array_values($sources),'text')),'selected_options'=>$selected,
                    'is_uncertain'=>$uncertain,'uncertainty_note'=>implode("\n",array_filter(array_column(array_values($sources),'note'))) ?: ($sources?'':'Belum terbaca. Konfirmasikan jika kosong.'),
                    'teacher_confirmed'=>false,'confirmed_at'=>null,
                ]);
            }
            $locked->grades()->delete();
            $locked->update(['page_ids'=>$pageIds,'reading_model'=>$request->user()->gemini_model,'edit_version'=>$locked->edit_version+1,'status'=>'pending_review']);
        });
        return response()->json([...$this->state($revision->fresh()),'reading_note'=>$reading['note'],'page_unclear'=>$reading['page_unclear']]);
    }
    public function review(Request $request,SubmissionRevision $revision,AssessmentAccess $access,SubmissionService $service) {
        $assessment=$this->authorizeRevision($request,$revision,$access);
        $data=$request->validate([
            'edit_version'=>'required|integer|min:0','answers'=>'required|array|max:100','answers.*.question_id'=>'required|integer|distinct',
            'answers.*.transcription'=>'present|nullable|string|max:30000','answers.*.selected_options'=>'present|array|max:20',
            'answers.*.selected_options.*'=>'required|string|max:10|distinct','answers.*.teacher_confirmed'=>'required|boolean',
        ]);
        $expected=$assessment->questions->pluck('id')->sort()->values()->all();
        $received=collect($data['answers'])->pluck('question_id')->map(fn($id)=>(int)$id)->sort()->values()->all();
        if($expected!==$received) throw ValidationException::withMessages(['answers'=>'Semua butir jawaban harus disertakan tepat satu kali.']);
        foreach($data['answers'] as $answer) {
            $q=$assessment->questions->find($answer['question_id']);
            if($q->isObjective() && (array_diff($answer['selected_options'],array_column($q->options,'key')) || (in_array($q->type,['multiple_choice','true_false'],true)&&count($answer['selected_options'])>1))) throw ValidationException::withMessages(['answers'=>'Pilihan jawaban tidak sesuai jenis atau opsi soal.']);
            if(!$q->isObjective() && $answer['selected_options']) throw ValidationException::withMessages(['answers'=>'Jawaban subjektif tidak menggunakan pilihan opsi.']);
        }
        DB::transaction(function() use($revision,$data,$service) {
            $locked=SubmissionRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail(); $service->editable($locked,(int)$data['edit_version']);
            foreach($data['answers'] as $item) {
                $locked->answers()->where('question_id',$item['question_id'])->update([
                    'transcription'=>$item['transcription'] ?? '','selected_options'=>json_encode($item['selected_options']),
                    'teacher_confirmed'=>$item['teacher_confirmed'],'confirmed_at'=>$item['teacher_confirmed']?now():null,
                ]);
            }
            $locked->grades()->delete();
            $ready=collect($data['answers'])->every(fn($a)=>$a['teacher_confirmed']);
            $locked->update(['status'=>$ready?'ready':'pending_review','edit_version'=>$locked->edit_version+1]);
        });
        return response()->json($this->state($revision->fresh()));
    }
    public function state(SubmissionRevision $revision):array {
        return ['revision'=>$revision->load(['answers','grades.changes']),'submission_id'=>$revision->submission_id,
            'history'=>$revision->submission->revisions()->orderByDesc('version')->get(['id','version','status','final_score','finalized_at'])];
    }
}
