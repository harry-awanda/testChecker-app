<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubmissionRevision extends Model {
    protected $fillable=['submission_id','version','edit_version','status','page_ids','reading_model','final_score','finalized_at'];
    protected function casts():array{return ['page_ids'=>'array','final_score'=>'float','finalized_at'=>'datetime'];}
    public function submission(){return $this->belongsTo(Submission::class);}
    public function answers(){return $this->hasMany(Answer::class);}
    public function grades(){return $this->hasMany(QuestionGrade::class);}
}
