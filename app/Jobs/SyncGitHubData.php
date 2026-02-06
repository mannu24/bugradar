<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Models\Review;
use App\Models\SyncLog;
use App\Services\GitHubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGitHubData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Integration $integration
    ) {}

    public function handle(GitHubService $githubService): void
    {
        $syncLog = SyncLog::create([
            'integration_id' => $this->integration->id,
            'sync_type' => 'full',
            'status' => 'running',
            'started_at' => now(),
            'prs_synced' => 0,
            'issues_synced' => 0,
            'reviews_synced' => 0,
        ]);

        try {
            // Sync Pull Requests
            $prs = $githubService->getPullRequests($this->integration);
            $prCount = $this->syncPullRequests($prs);

            // Sync Issues
            $issues = $githubService->getIssues($this->integration);
            $issueCount = $this->syncIssues($issues);

            // Sync Reviewed PRs
            $reviewedPrs = $githubService->getReviewedPullRequests($this->integration);
            $reviewCount = $this->syncReviews($reviewedPrs, $githubService);

            // Update sync log
            $syncLog->update([
                'status' => 'success',
                'completed_at' => now(),
                'prs_synced' => $prCount,
                'issues_synced' => $issueCount,
                'reviews_synced' => $reviewCount,
            ]);

            // Update integration last sync
            $this->integration->update([
                'last_synced_at' => now(),
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function syncPullRequests(array $prs): int
    {
        $count = 0;
        foreach ($prs as $pr) {
            // Parse repository info from URL
            $urlParts = parse_url($pr['html_url']);
            $pathParts = explode('/', trim($urlParts['path'], '/'));
            $owner = $pathParts[0] ?? null;
            $repo = $pathParts[1] ?? null;

            PullRequest::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform' => 'github',
                    'platform_pr_id' => (string) $pr['id'],
                ],
                [
                    'repository' => $owner && $repo ? "{$owner}/{$repo}" : null,
                    'title' => $pr['title'],
                    'description' => $pr['body'] ?? '',
                    'state' => $pr['state'],
                    'author_username' => $pr['user']['login'] ?? null,
                    'author_avatar' => $pr['user']['avatar_url'] ?? null,
                    'labels' => json_encode(array_column($pr['labels'] ?? [], 'name')),
                    'created_at_platform' => $pr['created_at'],
                    'updated_at_platform' => $pr['updated_at'],
                ]
            );
            $count++;
        }
        return $count;
    }

    private function syncIssues(array $issues): int
    {
        $count = 0;
        foreach ($issues as $issue) {
            // Skip pull requests (they come as issues in search)
            if (isset($issue['pull_request'])) {
                continue;
            }

            // Parse repository info
            $urlParts = parse_url($issue['html_url']);
            $pathParts = explode('/', trim($urlParts['path'], '/'));
            $owner = $pathParts[0] ?? null;
            $repo = $pathParts[1] ?? null;

            Issue::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform' => 'github',
                    'platform_issue_id' => (string) $issue['id'],
                ],
                [
                    'repository' => $owner && $repo ? "{$owner}/{$repo}" : null,
                    'title' => $issue['title'],
                    'description' => $issue['body'] ?? '',
                    'state' => $issue['state'],
                    'author_username' => $issue['user']['login'] ?? null,
                    'author_avatar' => $issue['user']['avatar_url'] ?? null,
                    'priority' => $this->determinePriority($issue['labels'] ?? []),
                    'type' => $this->determineIssueType($issue['labels'] ?? []),
                    'assignees' => json_encode(array_column($issue['assignees'] ?? [], 'login')),
                    'labels' => json_encode(array_column($issue['labels'] ?? [], 'name')),
                    'comments_count' => $issue['comments'] ?? 0,
                    'created_at_platform' => $issue['created_at'],
                    'updated_at_platform' => $issue['updated_at'],
                    'closed_at' => $issue['closed_at'],
                ]
            );
            $count++;
        }
        return $count;
    }

    private function syncReviews(array $prs, GitHubService $githubService): int
    {
        $count = 0;
        foreach ($prs as $pr) {
            // Parse repository info
            $urlParts = parse_url($pr['html_url']);
            $pathParts = explode('/', trim($urlParts['path'], '/'));
            $owner = $pathParts[0] ?? null;
            $repo = $pathParts[1] ?? null;

            if (!$owner || !$repo) {
                continue;
            }

            try {
                $reviews = $githubService->getPullRequestReviews(
                    $this->integration,
                    $owner,
                    $repo,
                    $pr['number']
                );

                foreach ($reviews as $review) {
                    // Only store reviews by the current user
                    if ($review['user']['login'] === $this->integration->username) {
                        // Find or create the PR first
                        $pullRequest = PullRequest::where('integration_id', $this->integration->id)
                            ->where('platform', 'github')
                            ->where('platform_pr_id', (string) $pr['id'])
                            ->first();

                        if (!$pullRequest) {
                            // Create the PR if it doesn't exist
                            $pullRequest = PullRequest::create([
                                'integration_id' => $this->integration->id,
                                'platform' => 'github',
                                'platform_pr_id' => (string) $pr['id'],
                                'repository' => "{$owner}/{$repo}",
                                'title' => $pr['title'],
                                'description' => $pr['body'] ?? '',
                                'state' => $pr['state'],
                                'author_username' => $pr['user']['login'] ?? 'unknown',
                                'author_avatar' => $pr['user']['avatar_url'] ?? null,
                                'labels' => json_encode(array_column($pr['labels'] ?? [], 'name')),
                                'created_at_platform' => $pr['created_at'],
                                'updated_at_platform' => $pr['updated_at'],
                            ]);
                        }

                        Review::updateOrCreate(
                            [
                                'pull_request_id' => $pullRequest->id,
                                'platform' => 'github',
                                'platform_review_id' => (string) $review['id'],
                            ],
                            [
                                'reviewer_username' => $review['user']['login'] ?? 'unknown',
                                'reviewer_avatar' => $review['user']['avatar_url'] ?? null,
                                'state' => strtolower($review['state']),
                                'body' => $review['body'] ?? '',
                                'submitted_at' => $review['submitted_at'],
                            ]
                        );
                        $count++;
                    }
                }
            } catch (\Exception $e) {
                // Continue with other PRs if one fails
                continue;
            }
        }
        return $count;
    }

    private function determinePriority(array $labels): string
    {
        $labelNames = array_map('strtolower', array_column($labels, 'name'));

        if (in_array('critical', $labelNames) || in_array('urgent', $labelNames)) {
            return 'critical';
        }
        if (in_array('high', $labelNames) || in_array('priority: high', $labelNames)) {
            return 'high';
        }
        if (in_array('low', $labelNames) || in_array('priority: low', $labelNames)) {
            return 'low';
        }

        return 'medium';
    }

    private function determineIssueType(array $labels): string
    {
        $labelNames = array_map('strtolower', array_column($labels, 'name'));

        if (in_array('bug', $labelNames)) {
            return 'bug';
        }
        if (in_array('feature', $labelNames) || in_array('enhancement', $labelNames)) {
            return 'feature';
        }
        if (in_array('task', $labelNames) || in_array('chore', $labelNames)) {
            return 'task';
        }

        return 'other';
    }
}
