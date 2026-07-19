<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Models\Review;
use App\Models\SyncLog;
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
            // access_token is already decrypted by the Integration model mutator
            $service = new BitbucketService($this->integration->access_token);

            // PR sync also backfills approvals as Review rows in the same pass
            [$prCount, $reviewCount] = $this->syncPullRequests($service);
            $issueCount = $this->syncIssues($service);

            $syncLog->update([
                'status' => 'success',
                'completed_at' => now(),
                'prs_synced' => $prCount,
                'issues_synced' => $issueCount,
                'reviews_synced' => $reviewCount,
            ]);

            $this->integration->update(['last_synced_at' => now()]);

            Log::info('Bitbucket sync completed', [
                'integration_id' => $this->integration->id,
                'prs'     => $prCount,
                'issues'  => $issueCount,
                'reviews' => $reviewCount,
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Bitbucket sync failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Sync pull requests AND their approvals (as Review rows).
     * Uses each PR's workspace+slug from the destination.repository field
     * to fetch activity and extract approvals in one pass.
     *
     * @return array{0:int,1:int}  [prCount, reviewCount]
     */
    protected function syncPullRequests(BitbucketService $service): array
    {
        $pullRequests = $service->getPullRequests('OPEN');

        if (!$pullRequests) {
            return [0, 0];
        }

        $prCount     = 0;
        $reviewCount = 0;

        foreach ($pullRequests as $pr) {
            // Bitbucket PR state comes as 'OPEN', 'MERGED', 'DECLINED', 'SUPERSEDED'
            $state = strtolower($pr['state'] ?? 'open');
            $state = $state === 'declined' ? 'closed' : $state;

            $pullRequest = PullRequest::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform'       => 'bitbucket',
                    'platform_pr_id' => (string) $pr['id'],
                ],
                [
                    'repository'          => $pr['destination']['repository']['full_name'] ?? '',
                    'title'               => $pr['title'],
                    'description'         => $pr['description'] ?? '',
                    'state'               => $state,
                    'author_username'     => $pr['author']['display_name'] ?? 'unknown',
                    'author_avatar'       => $pr['author']['links']['avatar']['href'] ?? null,
                    'branch_from'         => $pr['source']['branch']['name'] ?? null,
                    'branch_to'           => $pr['destination']['branch']['name'] ?? null,
                    'comments_count'      => $pr['comment_count'] ?? 0,
                    'labels'              => [],
                    'created_at_platform' => $pr['created_on'],
                    'updated_at_platform' => $pr['updated_on'],
                ]
            );
            $prCount++;

            $repoFullName = $pr['destination']['repository']['full_name'] ?? '';
            [$workspace, $slug] = array_pad(explode('/', $repoFullName, 2), 2, null);
            $prId = $pr['id'] ?? null;

            if ($workspace && $slug && $prId) {
                $reviewCount += $this->syncPrApprovals($service, $pullRequest, $workspace, $slug, (int) $prId);
            }
        }

        return [$prCount, $reviewCount];
    }

    /**
     * Fetch PR activity and persist approval entries as Review rows.
     * Best-effort — network/permission failures don't abort the whole sync.
     */
    protected function syncPrApprovals(BitbucketService $service, PullRequest $pr, string $workspace, string $slug, int $prId): int
    {
        try {
            $activity = $service->getPullRequestActivity($workspace, $slug, $prId);
        } catch (\Throwable $e) {
            Log::warning('Bitbucket PR activity fetch failed', ['pr' => $pr->platform_pr_id, 'error' => $e->getMessage()]);
            return 0;
        }

        if (!$activity) {
            return 0;
        }

        $count = 0;
        foreach ($activity as $entry) {
            // Each activity entry is one of: approval | changes_requested | comment | update
            if (!isset($entry['approval'])) {
                continue;
            }

            $approval = $entry['approval'];
            $user     = $approval['user'] ?? [];
            $userId   = trim($user['uuid'] ?? $user['account_id'] ?? 'unknown', '{}');

            Review::updateOrCreate(
                [
                    'pull_request_id'    => $pr->id,
                    'platform'           => 'bitbucket',
                    'platform_review_id' => 'approval-' . $userId . '-' . $prId,
                ],
                [
                    'reviewer_username' => $user['display_name'] ?? 'unknown',
                    'reviewer_avatar'   => $user['links']['avatar']['href'] ?? null,
                    'state'             => 'approved',
                    'body'              => '',
                    'submitted_at'      => $approval['date'] ?? now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function syncIssues(BitbucketService $service): int
    {
        // Bitbucket issues require workspace + repo slug, which we get from the user's repositories.
        // Fetch all repos the user is a member of, then sync issues from each.
        $repositories = $service->getRepositories();

        if (!$repositories) {
            return 0;
        }

        $count = 0;
        foreach ($repositories as $repo) {
            $workspace = $repo['workspace']['slug'] ?? null;
            $repoSlug = $repo['slug'] ?? null;

            if (!$workspace || !$repoSlug) {
                continue;
            }

            // Only sync repos that have issues enabled
            if (!($repo['has_issues'] ?? false)) {
                continue;
            }

            try {
                $issues = $service->getIssues($workspace, $repoSlug, 'new');
                if (!$issues) {
                    continue;
                }

                foreach ($issues as $issue) {
                    $priority = $this->mapBitbucketPriority($issue['priority'] ?? 'major');
                    $type = $this->mapBitbucketType($issue['kind'] ?? 'task');

                    Issue::updateOrCreate(
                        [
                            'integration_id' => $this->integration->id,
                            'platform' => 'bitbucket',
                            'platform_issue_id' => (string) $issue['id'],
                        ],
                        [
                            'repository' => "{$workspace}/{$repoSlug}",
                            'title' => $issue['title'],
                            'description' => $issue['content']['raw'] ?? '',
                            'state' => $this->mapBitbucketState($issue['state'] ?? 'new'),
                            'type' => $type,
                            'priority' => $priority,
                            'author_username' => $issue['reporter']['display_name'] ?? 'unknown',
                            'author_avatar' => $issue['reporter']['links']['avatar']['href'] ?? null,
                            'assignees' => isset($issue['assignee'])
                                ? [$issue['assignee']['display_name']]
                                : [],
                            'labels' => isset($issue['component']) ? [$issue['component']['name']] : [],
                            'comments_count' => $issue['comment_count'] ?? 0,
                            'created_at_platform' => $issue['created_on'],
                            'updated_at_platform' => $issue['updated_on'],
                        ]
                    );
                    $count++;
                }
            } catch (\Exception $e) {
                // Log and continue to next repo
                Log::warning('Bitbucket issue sync failed for repo', [
                    'repo' => "{$workspace}/{$repoSlug}",
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $count;
    }

    protected function mapBitbucketState(string $state): string
    {
        return match ($state) {
            'new', 'open' => 'open',
            'resolved', 'closed' => 'closed',
            'on hold', 'wontfix' => 'closed',
            'invalid', 'duplicate' => 'closed',
            default => 'open',
        };
    }

    protected function mapBitbucketPriority(string $priority): string
    {
        return match ($priority) {
            'blocker', 'critical' => 'critical',
            'major' => 'high',
            'minor' => 'medium',
            'trivial' => 'low',
            default => 'medium',
        };
    }

    protected function mapBitbucketType(string $kind): string
    {
        return match ($kind) {
            'bug' => 'bug',
            'enhancement' => 'feature',
            'proposal' => 'feature',
            'task' => 'task',
            default => 'task',
        };
    }
}
