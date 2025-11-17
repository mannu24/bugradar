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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // 'github', 'gitlab', 'bitbucket'
            $table->string('platform_issue_id'); // Issue ID on the platform
            $table->string('repository');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('task'); // 'bug', 'task', 'feature'
            $table->string('state'); // 'open', 'closed', 'in_progress'
            $table->string('priority')->nullable(); // 'low', 'medium', 'high', 'critical'
            $table->string('author_username');
            $table->string('author_avatar')->nullable();
            $table->json('assignees')->nullable(); // Array of usernames
            $table->json('labels')->nullable();
            $table->integer('comments_count')->default(0);
            $table->timestamp('due_date')->nullable();
            $table->timestamp('created_at_platform')->nullable();
            $table->timestamp('updated_at_platform')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'platform', 'platform_issue_id']);
            $table->index(['integration_id', 'state', 'type']);
            $table->index('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
