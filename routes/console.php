<?php

use App\Models\Integration;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Auto-sync all active integrations every 30 minutes ──────────────────────
Schedule::call(function () {
    Integration::where('is_active', true)->each(function (Integration $integration) {
        match ($integration->platform) {
            'github' => dispatch(new \App\Jobs\SyncGitHubData($integration)),
            'gitlab' => dispatch(new \App\Jobs\SyncGitLabData($integration)),
            'bitbucket' => dispatch(new \App\Jobs\SyncBitbucketData($integration)),
            default => null,
        };
    });
})->everyThirtyMinutes()->name('sync-all-integrations')->withoutOverlapping();
