<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
    use HasFactory, Notifiable;
    protected $attributes = ['role' => 'teacher', 'is_active' => true];
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'gemini_api_key', 'gemini_model', 'ai_consent_at'];
    protected $hidden = ['password', 'remember_token', 'gemini_api_key'];
    protected function casts(): array {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'is_active' => 'boolean', 'gemini_api_key' => 'encrypted', 'ai_consent_at' => 'datetime'];
    }
    public function classrooms() { return $this->belongsToMany(Classroom::class, 'classroom_teacher', 'teacher_id', 'classroom_id'); }
    public function assessments() { return $this->hasMany(Assessment::class, 'teacher_id'); }
}
