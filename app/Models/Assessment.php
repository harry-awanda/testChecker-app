<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assessment extends Model {
    protected $fillable = ['teacher_id','classroom_id','source_assessment_id','title','subject','type','status','version','approved_at','locked_at'];
    protected function casts(): array { return ['approved_at'=>'datetime','locked_at'=>'datetime']; }
    public function teacher() { return $this->belongsTo(User::class,'teacher_id'); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function questions() { return $this->hasMany(Question::class)->orderBy('number'); }
    public function submissions() { return $this->hasMany(Submission::class); }
}
