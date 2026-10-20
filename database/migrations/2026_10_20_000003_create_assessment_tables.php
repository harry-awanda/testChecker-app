<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id(); $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete(); $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('title'); $table->string('subject'); $table->string('type'); $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1); $table->timestamp('approved_at')->nullable(); $table->timestamp('locked_at')->nullable(); $table->timestamps();
        });
        Schema::create('questions', function (Blueprint $table) {
            $table->id(); $table->foreignId('assessment_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('number'); $table->string('type');
            $table->text('prompt'); $table->decimal('weight',10,4); $table->json('options'); $table->json('answer_key'); $table->text('reference_answer')->nullable();
            $table->json('criteria'); $table->string('scoring_mode')->default('all_or_nothing'); $table->timestamps(); $table->unique(['assessment_id','number']);
        });
    }
    public function down(): void { Schema::dropIfExists('questions'); Schema::dropIfExists('assessments'); }
};
