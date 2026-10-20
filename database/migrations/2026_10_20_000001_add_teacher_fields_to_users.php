<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('teacher');
            $table->boolean('is_active')->default(true);
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model')->nullable();
            $table->timestamp('ai_consent_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active', 'gemini_api_key', 'gemini_model', 'ai_consent_at']));
    }
};
