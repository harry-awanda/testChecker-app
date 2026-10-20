<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class Submission extends Model {
    protected $fillable=['assessment_id','student_id','enrollment_id'];
    public function assessment(){return $this->belongsTo(Assessment::class);}
    public function student(){return $this->belongsTo(Student::class);}
    public function enrollment(){return $this->belongsTo(Enrollment::class);}
    public function revisions(){return $this->hasMany(SubmissionRevision::class);}
    public function latestRevision(){return $this->hasOne(SubmissionRevision::class)->ofMany('version','max');}
    public function effectiveRevision(){return $this->hasOne(SubmissionRevision::class)->ofMany(['version'=>'max','id'=>'max'],fn(Builder $q)=>$q->where('status','finalized'));}
}
