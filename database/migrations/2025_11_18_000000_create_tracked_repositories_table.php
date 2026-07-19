<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // github, gitlab, bitbucket
            $table->string('repo_full_name');          // e.g. "manu/my-app"
            $table->string('repo_platform_id')->nullable(); // repo id on the platform
            $table->string('repo_url')->nullable();
            $table->boolean('is_active')->default(true); // user's toggle state
            $table->string('webhook_id')->nullable();    // hook id returned by platform
            $table->boolean('webhook_active')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'repo_full_name']);
            $table->index(['integration_id', 'is_active']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_repositories');
    }
};
