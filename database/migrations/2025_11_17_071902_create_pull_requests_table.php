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
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // 'github', 'gitlab', 'bitbucket'
            $table->string('platform_pr_id'); // PR ID on the platform
            $table->string('repository');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('state'); // 'open', 'closed', 'merged', 'draft'
            $table->string('author_username');
            $table->string('author_avatar')->nullable();
            $table->string('branch_from')->nullable();
            $table->string('branch_to')->nullable();
            $table->integer('commits_count')->default(0);
            $table->integer('additions')->default(0);
            $table->integer('deletions')->default(0);
            $table->integer('comments_count')->default(0);
            $table->string('review_status')->nullable(); // 'approved', 'changes_requested', 'pending'
            $table->json('labels')->nullable();
            $table->timestamp('created_at_platform')->nullable();
            $table->timestamp('updated_at_platform')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'platform', 'platform_pr_id']);
            $table->index(['integration_id', 'state']);
            $table->index('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
