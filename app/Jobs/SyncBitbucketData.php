<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Services\BitbucketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBitbucketData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Integration $integration
    ) {}

    public function handle(): void
    {
        try {
            $service = new BitbucketService(
                decrypt($this->integration->access_token)
            );

            // Sync pull requests
            $this->syncPullRequests($service);

            // Update last sync time
            $this->integration->update([
                'last_synced_at' => now()
            ]);

            Log::info('Bitbucket sync completed', [
                'integration_id' => $this->integration->id
            ]);
        } catch (\Exception $e) {
            Log::error('Bitbucket sync failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    protected function syncPullRequests(BitbucketService $service)
    {
        $pullRequests = $service->getPullRequests('OPEN');

        if (!$pullRequests) {
            return;
        }

        foreach ($pullRequests as $pr) {
            PullRequest::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform_pr_id' => (string) $pr['id']
                ],
                [
                    'title' => $pr['title'],
                    'description' => $pr['description'] ?? '',
                    'status' => strtolower($pr['state']),
                    'url' => $pr['links']['html']['href'] ?? '',
                    'repository' => $pr['destination']['repository']['full_name'] ?? '',
                    'author' => $pr['author']['display_name'] ?? '',
                    'created_at_platform' => $pr['created_on'],
                    'updated_at_platform' => $pr['updated_on'],
                    'is_draft' => false,
                    'metadata' => [
                        'source_branch' => $pr['source']['branch']['name'] ?? '',
                        'destination_branch' => $pr['destination']['branch']['name'] ?? '',
                        'comment_count' => $pr['comment_count'] ?? 0,
                        'task_count' => $pr['task_count'] ?? 0,
                        'close_source_branch' => $pr['close_source_branch'] ?? false,
                    ]
                ]
            );
        }
    }
}
