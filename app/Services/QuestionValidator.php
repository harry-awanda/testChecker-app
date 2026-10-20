<?php
namespace App\Services;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class QuestionValidator {
    public function validate(array $questions): array {
        $data = Validator::make(['questions'=>$questions], [
            'questions'=>'required|array|min:1|max:100', 'questions.*.number'=>'required|integer|min:1|distinct',
            'questions.*.type'=>['required',Rule::in(array_keys(config('testchecker.question_types')))],
            'questions.*.prompt'=>'required|string|max:20000','questions.*.weight'=>'required|numeric|min:0.01|max:10000',
            'questions.*.options'=>'present|array|max:20', 'questions.*.options.*.key'=>'required|string|max:10', 'questions.*.options.*.text'=>'required|string|max:5000',
            'questions.*.answer_key'=>'present|array|max:20','questions.*.answer_key.*'=>'required|string|max:10',
            'questions.*.reference_answer'=>'nullable|string|max:30000', 'questions.*.criteria'=>'present|array|max:20',
            'questions.*.criteria.*.label'=>'required|string|max:255', 'questions.*.criteria.*.description'=>'required|string|max:5000',
            'questions.*.criteria.*.max_score'=>'required|numeric|min:0.01|max:10000',
            'questions.*.scoring_mode'=>['required',Rule::in(['all_or_nothing','partial'])],
        ])->validate()['questions'];
        foreach ($data as $index => &$question) {
            $question['number'] = (int)$question['number'];
            $question['weight'] = (float)$question['weight'];
            $keys = array_column($question['options'],'key');
            if (count($keys) !== count(array_unique($keys))) $this->fail($index,'Kode opsi harus unik.');
            if (count($question['answer_key']) !== count(array_unique($question['answer_key']))) $this->fail($index,'Kunci jawaban berulang.');
            if (in_array($question['type'],['multiple_choice','complex_choice'],true)) {
                if (count($keys)<2 || !$question['answer_key'] || array_diff($question['answer_key'],$keys)) $this->fail($index,'Opsi dan kunci pilihan tidak sesuai.');
                if ($question['type']==='multiple_choice' && count($question['answer_key'])!==1) $this->fail($index,'Pilihan ganda harus memiliki satu kunci.');
            } elseif ($question['type']==='true_false') {
                if (count($question['answer_key'])!==1 || !in_array($question['answer_key'][0],['true','false'],true)) $this->fail($index,'Kunci benar/salah harus true atau false.');
                $question['options'] = [['key'=>'true','text'=>'Benar'],['key'=>'false','text'=>'Salah']];
            } else {
                if (!$question['criteria'] || abs(array_sum(array_column($question['criteria'],'max_score'))-$question['weight'])>0.00001) $this->fail($index,'Total skor kriteria harus sama dengan bobot soal.');
                $question['answer_key'] = []; $question['options'] = [];
            }
        }
        unset($question);
        return $data;
    }
    private function fail(int $index,string $message): never { throw ValidationException::withMessages(['questions.'.($index).'.prompt'=>'Soal '.($index+1).': '.$message]); }
}
