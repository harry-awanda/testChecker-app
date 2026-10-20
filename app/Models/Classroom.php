<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Classroom extends Model {
    protected $fillable = ['academic_year_id','name','is_archived'];
    protected function casts(): array { return ['is_archived'=>'boolean']; }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function teachers() { return $this->belongsToMany(User::class,'classroom_teacher','classroom_id','teacher_id'); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
}
