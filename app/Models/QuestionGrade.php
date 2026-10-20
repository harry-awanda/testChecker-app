<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionGrade extends Model {
    protected $fillable=['submission_revision_id','question_id','score','criteria_scores','feedback','source','model_name'];
    protected function casts():array{return ['score'=>'float','criteria_scores'=>'array'];}
    public function changes(){return $this->hasMany(GradeChange::class);}
}
