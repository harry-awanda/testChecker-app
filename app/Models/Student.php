<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Student extends Model {
    protected $fillable = ['student_code','name','is_archived'];
    protected function casts(): array { return ['is_archived'=>'boolean']; }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
}
