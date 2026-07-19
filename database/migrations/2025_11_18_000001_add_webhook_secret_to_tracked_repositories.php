<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_repositories', function (Blueprint $table) {
            // Per-webhook secret used to verify incoming webhook payloads (encrypted at rest)
            $table->text('webhook_secret')->nullable()->after('webhook_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracked_repositories', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
