<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Services\GitLabService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGitLabData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Integration $integration
    ) {}

    public function handle(): void
    {
        try {
            $service = new GitLabService(
                decrypt($this->integration->access_token),
                $this->integration->metadata['instance_url'] ?? 'https://gitlab.com/api/v4'
            );

            // Sync merge requests (GitLab's equivalent of PRs)
            $this->syncMergeRequests($service);

            // Sync issues
            $this->syncIssues($service);

            // Update last sync time
            $this->integration->update([
                'last_synced_at' => now()
            ]);

            Log::info('GitLab sync completed', [
                'integration_id' => $this->integration->id
            ]);
        } catch (\Exception $e) {
            Log::error('GitLab sync failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    protected function syncMergeRequests(GitLabService $service)
    {
        $mergeRequests = $service->getMergeRequests('opened', 'all');

        if (!$mergeRequests) {
            return;
        }

        foreach ($mergeRequests as $mr) {
            PullRequest::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform_pr_id' => (string) $mr['id']
                ],
                [
                    'title' => $mr['title'],
                    'description' => $mr['description'] ?? '',
                    'status' => $mr['state'],
                    'url' => $mr['web_url'],
                    'repository' => $mr['references']['full'] ?? '',
                    'author' => $mr['author']['username'] ?? '',
                    'created_at_platform' => $mr['created_at'],
                    'updated_at_platform' => $mr['updated_at'],
                    'is_draft' => $mr['draft'] ?? false,
                    'metadata' => [
                        'project_id' => $mr['project_id'],
                        'iid' => $mr['iid'],
                        'source_branch' => $mr['source_branch'],
                        'target_branch' => $mr['target_branch'],
                        'merge_status' => $mr['merge_status'] ?? null,
                        'labels' => $mr['labels'] ?? [],
                        'upvotes' => $mr['upvotes'] ?? 0,
                        'downvotes' => $mr['downvotes'] ?? 0,
                    ]
                ]
            );
        }
    }

    protected function syncIssues(GitLabService $service)
    {
        $issues = $service->getIssues('opened', 'all');

        if (!$issues) {
            return;
        }

        foreach ($issues as $issue) {
            $labels = $issue['labels'] ?? [];
            $type = $this->determineIssueType($labels);

            Issue::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform_issue_id' => (string) $issue['id']
                ],
                [
                    'title' => $issue['title'],
                    'description' => $issue['description'] ?? '',
                    'status' => $issue['state'],
                    'type' => $type,
                    'priority' => $this->determinePriority($labels),
                    'url' => $issue['web_url'],
                    'repository' => $issue['references']['full'] ?? '',
                    'author' => $issue['author']['username'] ?? '',
                    'assignees' => array_map(fn($a) => $a['username'], $issue['assignees'] ?? []),
                    'labels' => $labels,
                    'created_at_platform' => $issue['created_at'],
                    'updated_at_platform' => $issue['updated_at'],
                    'metadata' => [
                        'project_id' => $issue['project_id'],
                        'iid' => $issue['iid'],
                        'upvotes' => $issue['upvotes'] ?? 0,
                        'downvotes' => $issue['downvotes'] ?? 0,
                        'due_date' => $issue['due_date'] ?? null,
                    ]
                ]
            );
        }
    }

    protected function determineIssueType(array $labels): string
    {
        $labelStr = strtolower(implode(' ', $labels));

        if (str_contains($labelStr, 'bug')) {
            return 'bug';
        } elseif (str_contains($labelStr, 'feature')) {
            return 'feature';
        } elseif (str_contains($labelStr, 'task')) {
            return 'task';
        }

        return 'task';
    }

    protected function determinePriority(array $labels): string
    {
        $labelStr = strtolower(implode(' ', $labels));

        if (str_contains($labelStr, 'critical') || str_contains($labelStr, 'urgent')) {
            return 'critical';
        } elseif (str_contains($labelStr, 'high')) {
            return 'high';
        } elseif (str_contains($labelStr, 'low')) {
            return 'low';
        }

        return 'medium';
    }
}
