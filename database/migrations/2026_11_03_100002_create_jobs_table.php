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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('platform')->default('other')->index();
            $table->string('url')->unique();
            $table->string('location')->nullable();
            $table->string('experience_range')->nullable();
            $table->string('salary_text')->nullable();
            $table->longText('jd_text')->nullable();
            $table->json('must_have_skills')->nullable();
            $table->json('nice_to_have_skills')->nullable();
            $table->unsignedTinyInteger('match_score')->nullable()->index();
            $table->json('matched_skills')->nullable();
            $table->json('gaps')->nullable();
            $table->timestamp('found_at')->useCurrent();
            $table->string('status')->default('found')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
