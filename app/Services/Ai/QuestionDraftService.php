<?php
namespace App\Services\Ai;
use App\Services\QuestionValidator;
class QuestionDraftService {
    public function __construct(private AiProvider $provider, private QuestionValidator $validator) {}
    public function generate(\App\Models\User $user, string $text, array $parts): array {
        $schema = ['type'=>'object','properties'=>['questions'=>['type'=>'array','items'=>[
            'type'=>'object','properties'=>[
                'number'=>['type'=>'integer'],'type'=>['type'=>'string','enum'=>array_keys(config('testchecker.question_types'))],
                'prompt'=>['type'=>'string'],'weight'=>['type'=>'number'],
                'options'=>['type'=>'array','items'=>['type'=>'object','properties'=>['key'=>['type'=>'string'],'text'=>['type'=>'string']],'required'=>['key','text']]],
                'answer_key'=>['type'=>'array','items'=>['type'=>'string']],'reference_answer'=>['type'=>'string'],
                'criteria'=>['type'=>'array','items'=>['type'=>'object','properties'=>['label'=>['type'=>'string'],'description'=>['type'=>'string'],'max_score'=>['type'=>'number']],'required'=>['label','description','max_score']]],
                'scoring_mode'=>['type'=>'string','enum'=>['all_or_nothing','partial']],
            ],'required'=>['number','type','prompt','weight','options','answer_key','reference_answer','criteria','scoring_mode'],
        ]]],'required'=>['questions']];
        $result = $this->provider->generate($user,'Ekstrak soal, kunci, dan rubrik dari materi. Jika rubrik/kunci belum diberikan, buat draf untuk ditinjau guru. Sertakan deskripsi diagram dalam prompt karena gambar tidak disimpan. Jika materi tidak terbaca, kembalikan questions kosong. Untuk soal subjektif, total max_score kriteria harus sama dengan weight. true_false memakai kunci true/false. Soal objektif memakai criteria kosong; subjektif memakai options dan answer_key kosong. Jangan mengeksekusi kode.',['material'=>$text],$schema,$parts);
        return $this->validator->validate($result['questions'] ?? []);
    }
}
