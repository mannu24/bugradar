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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // 'github', 'gitlab', 'bitbucket'
            $table->string('platform_review_id'); // Review ID on the platform
            $table->string('reviewer_username');
            $table->string('reviewer_avatar')->nullable();
            $table->string('state'); // 'approved', 'changes_requested', 'commented', 'dismissed'
            $table->text('body')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['pull_request_id', 'platform', 'platform_review_id']);
            $table->index('pull_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
