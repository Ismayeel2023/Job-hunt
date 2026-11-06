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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('gmail_account')->index();
            $table->string('gmail_message_id')->unique();
            $table->string('thread_id')->nullable()->index();
            $table->string('from')->nullable();
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->foreignId('matched_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('classification')->nullable();
            $table->boolean('processed')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
