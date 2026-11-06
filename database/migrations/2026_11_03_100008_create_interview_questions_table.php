<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interview_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('round_type')->nullable()->index();
            $table->text('question');
            $table->string('topic')->nullable()->index();
            $table->string('difficulty')->nullable();
            $table->unsignedSmallInteger('year_reported')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->longText('suggested_answer')->nullable();
            $table->json('answer_based_on')->nullable();
            $table->boolean('practiced')->default(false)->index();
            $table->text('my_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_questions');
    }
};
