<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->date('starts_on'); $table->date('ends_on'); $table->boolean('is_archived')->default(false); $table->timestamps();
        });
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id(); $table->foreignId('academic_year_id')->constrained()->restrictOnDelete(); $table->string('name'); $table->boolean('is_archived')->default(false); $table->timestamps();
            $table->unique(['academic_year_id', 'name']);
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id(); $table->string('student_code')->unique(); $table->string('name'); $table->boolean('is_archived')->default(false); $table->timestamps();
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id(); $table->foreignId('student_id')->constrained()->restrictOnDelete(); $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at'); $table->timestamp('ended_at')->nullable(); $table->timestamps(); $table->index(['classroom_id', 'ended_at']);
        });
        Schema::create('classroom_teacher', function (Blueprint $table) {
            $table->id(); $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete(); $table->foreignId('classroom_id')->constrained()->restrictOnDelete(); $table->unique(['teacher_id','classroom_id']);
        });
    }
    public function down(): void { foreach (['classroom_teacher','enrollments','students','classrooms','academic_years'] as $table) Schema::dropIfExists($table); }
};
