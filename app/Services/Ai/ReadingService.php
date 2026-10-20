<?php
namespace App\Services\Ai;
use App\Models\{Assessment,User};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class ReadingService {
    public function __construct(private AiProvider $provider) {}
    public function read(User $user,Assessment $assessment,array $image):array {
        $schema=['type'=>'object','properties'=>[
            'page_unclear'=>['type'=>'boolean'],'note'=>['type'=>'string'],
            'answers'=>['type'=>'array','items'=>['type'=>'object','properties'=>[
                'question_number'=>['type'=>'integer'],'transcription'=>['type'=>'string'],
                'selected_options'=>['type'=>'array','items'=>['type'=>'string']],
                'uncertain'=>['type'=>'boolean'],'uncertainty_note'=>['type'=>'string'],'location'=>['type'=>'string'],
            ],'required'=>['question_number','transcription','selected_options','uncertain','uncertainty_note','location']]],
        ],'required'=>['page_unclear','note','answers']];
        $questions=$assessment->questions->map(fn($q)=>$q->only(['number','type','prompt','options']))->all();
        $result=$this->provider->generate($user,
            'Baca satu halaman jawaban siswa secara literal, tanpa menilai dan tanpa melihat kunci jawaban. Petakan hanya nomor soal yang dapat dipastikan. Jangan menebak tulisan, tanda pilihan, atau nomor soal yang kabur. Tandai uncertain dan jelaskan masalahnya. Untuk diagram deskripsikan label, hubungan, dan struktur yang terlihat, tanpa menambahkan isi. Untuk kode pertahankan indentasi dan simbol; untuk rumus pertahankan langkah. Jika halaman tidak terbaca, page_unclear=true. Abaikan dan jangan transkripsikan nama, NIS, atau identitas lain. location berupa lokasi pada halaman. selected_options menggunakan kode opsi; true_false memakai true/false.',
            ['questions'=>$questions],$schema,[$image]);
        $data=Validator::make($result,[
            'page_unclear'=>'required|boolean','note'=>'present|string|max:5000','answers'=>'present|array|max:100',
            'answers.*.question_number'=>'required|integer|distinct','answers.*.transcription'=>'present|string|max:30000',
            'answers.*.selected_options'=>'present|array|max:20','answers.*.selected_options.*'=>'required|string|max:10',
            'answers.*.uncertain'=>'required|boolean','answers.*.uncertainty_note'=>'present|string|max:5000','answers.*.location'=>'present|string|max:1000',
        ])->validate();
        foreach($data['answers'] as $answer) {
            $q=$assessment->questions->firstWhere('number',$answer['question_number']);
            if(count($answer['selected_options'])!==count(array_unique($answer['selected_options']))) throw ValidationException::withMessages(['ai'=>'AI mengembalikan pilihan berulang dalam satu soal.']);
            if(!$q || (!$q->isObjective() && $answer['selected_options']) || ($q->isObjective() && array_diff($answer['selected_options'],array_column($q->options,'key')))) throw ValidationException::withMessages(['ai'=>'AI mengembalikan nomor atau opsi yang tidak sesuai. Tidak ada hasil pembacaan yang disimpan.']);
        }
        return $data;
    }
}
