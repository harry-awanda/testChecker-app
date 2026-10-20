<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Enrollment extends Model {
    protected $fillable = ['student_id','classroom_id','started_at','ended_at'];
    protected function casts(): array { return ['started_at'=>'datetime','ended_at'=>'datetime']; }
    public function student() { return $this->belongsTo(Student::class); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
}
