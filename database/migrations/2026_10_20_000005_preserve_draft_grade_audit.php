<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up():void {
        if(Schema::hasColumn('grade_changes','submission_revision_id'))return;
        Schema::table('grade_changes',function(Blueprint $table) {
            $table->foreignId('submission_revision_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->cascadeOnDelete();
        });
        DB::table('grade_changes')->orderBy('id')->chunkById(100,function($changes) {
            foreach($changes as $change) {
                $grade=DB::table('question_grades')->find($change->question_grade_id);
                if($grade)DB::table('grade_changes')->where('id',$change->id)->update(['submission_revision_id'=>$grade->submission_revision_id,'question_id'=>$grade->question_id]);
            }
        });
        Schema::table('grade_changes',function(Blueprint $table) {$table->dropForeign(['question_grade_id']);});
        Schema::table('grade_changes',function(Blueprint $table) {$table->unsignedBigInteger('question_grade_id')->nullable()->change();});
        Schema::table('grade_changes',function(Blueprint $table) {$table->foreign('question_grade_id')->references('id')->on('question_grades')->nullOnDelete();});
    }
    public function down():void {
        Schema::table('grade_changes',function(Blueprint $table) {
            $table->dropConstrainedForeignId('submission_revision_id');$table->dropConstrainedForeignId('question_id');
        });
    }
};
