<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use App\Services\{AssessmentAccess,QuestionValidator};
use App\Services\Ai\{DocumentInput,QuestionDraftService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class AssessmentController extends Controller {
    public function index(Request $request) {
        $assigned = $request->user()->classrooms()->pluck('classrooms.id');
        return view('teacher.assessments.index', [
            'assessments'=>$request->user()->assessments()->whereIn('classroom_id',$assigned)->with('classroom.academicYear')->latest()->paginate(20),
            'classrooms'=>$request->user()->classrooms()->where('classrooms.is_archived',false)->whereHas('academicYear',fn($q)=>$q->where('is_archived',false))->with('academicYear')->get(),
        ]);
    }
    public function store(Request $request) {
        $data = $request->validate(['title'=>'required|string|max:255','subject'=>'required|string|max:255','type'=>['required',Rule::in(array_keys(config('testchecker.assessment_types')))],'classroom_id'=>'required|integer']);
        $request->user()->classrooms()->where('classrooms.is_archived',false)->whereHas('academicYear',fn($q)=>$q->where('is_archived',false))->findOrFail($data['classroom_id']);
        $assessment = $request->user()->assessments()->create($data);
        return redirect()->route('assessments.edit',$assessment);
    }
    public function show(Request $request, Assessment $assessment, AssessmentAccess $access) {
        $access->authorize($request->user(),$assessment);
        return view('teacher.assessments.show',['assessment'=>$assessment->load('questions','classroom.academicYear')]);
    }
    public function edit(Request $request, Assessment $assessment, AssessmentAccess $access) {
        $access->authorize($request->user(),$assessment);
        abort_if($assessment->locked_at,409,'Tes sudah digunakan. Buat versi baru untuk mengubah soal atau rubrik.');
        return view('teacher.assessments.edit',['assessment'=>$assessment->load('questions')]);
    }
    public function update(Request $request, Assessment $assessment, AssessmentAccess $access, QuestionValidator $validator) {
        $access->authorize($request->user(),$assessment);
        $data = $request->validate(['title'=>'required|string|max:255','subject'=>'required|string|max:255','questions_json'=>'required|string|max:2000000','intent'=>['required',Rule::in(['save','approve'])]]);
        try { $questions = json_decode($data['questions_json'],true,64,JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw ValidationException::withMessages(['questions'=>'Format soal tidak valid.']); }
        if (!is_array($questions)) throw ValidationException::withMessages(['questions'=>'Soal harus berupa daftar.']);
        $questions = $validator->validate($questions);
        DB::transaction(function () use ($assessment,$questions,$data) {
            $locked = Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->locked_at,409,'Tes sudah digunakan. Buat versi baru.');
            $locked->questions()->delete();
            $locked->questions()->createMany($questions);
            $locked->update(['title'=>$data['title'],'subject'=>$data['subject'],'status'=>$data['intent']==='approve'?'approved':'draft','approved_at'=>$data['intent']==='approve'?now():null]);
        });
        return redirect()->route($data['intent']==='approve'?'assessments.show':'assessments.edit',$assessment)->with('success',$data['intent']==='approve'?'Soal dan rubrik disetujui. Tes siap dikoreksi.':'Draf soal disimpan.');
    }
    public function draft(Request $request, Assessment $assessment, AssessmentAccess $access, DocumentInput $input, QuestionDraftService $service) {
        $access->authorize($request->user(),$assessment);
        abort_if($assessment->locked_at,409);
        $data = $request->validate(['text'=>'nullable|string|max:100000','document'=>'nullable|array']);
        if (empty($data['text']) && empty($data['document'])) throw ValidationException::withMessages(['text'=>'Masukkan materi atau pilih dokumen.']);
        $parts = empty($data['document'])?[]:[$input->part($data['document'],true)];
        return response()->json(['questions'=>$service->generate($request->user(),$data['text'] ?? '',$parts)]);
    }
    public function duplicate(Request $request, Assessment $assessment, AssessmentAccess $access) {
        $access->authorize($request->user(),$assessment);
        $copy = DB::transaction(function () use ($assessment) {
            $locked = Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $copy = $locked->replicate(['approved_at','locked_at']);
            $copy->source_assessment_id=$locked->id; $copy->version=$locked->version+1; $copy->status='draft'; $copy->title=$locked->title.' (revisi)'; $copy->save();
            foreach ($locked->questions as $question) { $new=$question->replicate(); $new->assessment_id=$copy->id; $new->save(); }
            return $copy;
        });
        return redirect()->route('assessments.edit',$copy)->with('success','Versi tes baru dibuat. Penilaian lama tetap tersimpan.');
    }
    public function destroy(Request $request, Assessment $assessment, AssessmentAccess $access) {
        $access->authorize($request->user(),$assessment);
        $request->validate(['confirmation'=>['required',Rule::in(['HAPUS'])]]);
        DB::transaction(function () use ($assessment) { Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail()->delete(); });
        return redirect()->route('assessments.index')->with('success','Tes dan seluruh data penilaiannya dihapus.');
    }
}
