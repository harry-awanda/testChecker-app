<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('assessment_roster',function(Blueprint $table) {
            $table->id(); $table->foreignId('assessment_id')->constrained()->cascadeOnDelete(); $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete(); $table->string('student_name'); $table->string('student_code'); $table->unique(['assessment_id','student_id']);
        });
        Schema::create('submissions',function(Blueprint $table) {
            $table->id(); $table->foreignId('assessment_id')->constrained()->cascadeOnDelete(); $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete(); $table->timestamps(); $table->unique(['assessment_id','student_id']);
        });
        Schema::create('submission_revisions',function(Blueprint $table) {
            $table->id(); $table->foreignId('submission_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('version'); $table->unsignedInteger('edit_version')->default(0);
            $table->string('status')->default('pending_review'); $table->json('page_ids'); $table->string('reading_model')->nullable();
            $table->decimal('final_score',8,2)->nullable(); $table->timestamp('finalized_at')->nullable(); $table->timestamps(); $table->unique(['submission_id','version']);
        });
        Schema::create('answers',function(Blueprint $table) {
            $table->id(); $table->foreignId('submission_revision_id')->constrained()->cascadeOnDelete(); $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('transcription'); $table->json('selected_options'); $table->json('sources'); $table->boolean('is_uncertain')->default(true);
            $table->text('uncertainty_note')->nullable(); $table->boolean('teacher_confirmed')->default(false); $table->timestamp('confirmed_at')->nullable(); $table->timestamps();
            $table->unique(['submission_revision_id','question_id']);
        });
        Schema::create('question_grades',function(Blueprint $table) {
            $table->id(); $table->foreignId('submission_revision_id')->constrained()->cascadeOnDelete(); $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->decimal('score',12,4); $table->json('criteria_scores'); $table->text('feedback'); $table->string('source'); $table->string('model_name')->nullable();
            $table->timestamps(); $table->unique(['submission_revision_id','question_id']);
        });
        Schema::create('grade_changes',function(Blueprint $table) {
            $table->id(); $table->foreignId('question_grade_id')->constrained()->cascadeOnDelete(); $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->json('before'); $table->json('after'); $table->text('reason'); $table->timestamp('created_at');
        });
    }
    public function down(): void { foreach(['grade_changes','question_grades','answers','submission_revisions','submissions','assessment_roster'] as $table) Schema::dropIfExists($table); }
};
