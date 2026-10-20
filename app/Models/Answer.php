<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Answer extends Model {
    protected $fillable=['submission_revision_id','question_id','transcription','selected_options','sources','is_uncertain','uncertainty_note','teacher_confirmed','confirmed_at'];
    protected function casts():array{return ['selected_options'=>'array','sources'=>'array','is_uncertain'=>'boolean','teacher_confirmed'=>'boolean','confirmed_at'=>'datetime'];}
    public function question(){return $this->belongsTo(Question::class);}
}
