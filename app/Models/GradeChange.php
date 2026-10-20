<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradeChange extends Model {
    public $timestamps=false;
    protected $fillable=['question_grade_id','teacher_id','before','after','reason','created_at'];
    protected function casts():array{return ['before'=>'array','after'=>'array','created_at'=>'datetime'];}
}
