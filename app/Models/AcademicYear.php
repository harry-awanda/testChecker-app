<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicYear extends Model {
    protected $fillable = ['name','starts_on','ends_on','is_archived'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date','is_archived'=>'boolean']; }
    public function classrooms() { return $this->hasMany(Classroom::class); }
}
