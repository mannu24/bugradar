<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Models\Review;
use App\Models\SyncLog;
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
            $instanceUrl = config('services.gitlab.base_uri', 'https://gitlab.com/api/v4');

            $service = new GitLabService(
                $this->integration->access_token,
                $instanceUrl
            );

            // MR sync also backfills approvals as Review rows in the same pass
            [$prCount, $reviewCount] = $this->syncMergeRequests($service);
            $issueCount = $this->syncIssues($service);

            $syncLog->update([
                'status' => 'success',
                'completed_at' => now(),
                'prs_synced' => $prCount,
                'issues_synced' => $issueCount,
                'reviews_synced' => $reviewCount,
            ]);

            $this->integration->update(['last_synced_at' => now()]);

            Log::info('GitLab sync completed', [
                'integration_id' => $this->integration->id,
                'prs' => $prCount,
                'issues' => $issueCount,
                'reviews' => $reviewCount,
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('GitLab sync failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Sync merge requests AND their approvals (as Review rows).
     * Runs in one pass so we can use each MR's project_id/iid to hit /approvals.
     *
     * @return array{0:int,1:int}  [prCount, reviewCount]
     */
    protected function syncMergeRequests(GitLabService $service): array
    {
        $mergeRequests = $service->getMergeRequests('opened', 'all');

        if (!$mergeRequests) {
            return [0, 0];
        }

        $prCount     = 0;
        $reviewCount = 0;

        foreach ($mergeRequests as $mr) {
            $pr = PullRequest::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform'       => 'gitlab',
                    'platform_pr_id' => (string) $mr['id'],
                ],
                [
                    'repository'          => $mr['references']['full'] ?? ($mr['web_url'] ?? ''),
                    'title'               => $mr['title'],
                    'description'         => $mr['description'] ?? '',
                    'state'               => $this->mapMrState($mr['state']),
                    'author_username'     => $mr['author']['username'] ?? 'unknown',
                    'author_avatar'       => $mr['author']['avatar_url'] ?? null,
                    'branch_from'         => $mr['source_branch'] ?? null,
                    'branch_to'           => $mr['target_branch'] ?? null,
                    'labels'              => $mr['labels'] ?? [],
                    'created_at_platform' => $mr['created_at'],
                    'updated_at_platform' => $mr['updated_at'],
                ]
            );
            $prCount++;

            $projectId = $mr['project_id'] ?? null;
            $mrIid     = $mr['iid'] ?? null;

            if ($projectId && $mrIid) {
                $reviewCount += $this->syncMrApprovals($service, $pr, $projectId, $mrIid);
            }
        }

        return [$prCount, $reviewCount];
    }

    /**
     * Fetch approvals for a single MR and persist them as Review rows.
     * Best-effort — network/permission failures don't abort the whole sync.
     */
    protected function syncMrApprovals(GitLabService $service, PullRequest $pr, int|string $projectId, int|string $mrIid): int
    {
        try {
            $approvals = $service->getMergeRequestApprovals($projectId, $mrIid);
        } catch (\Throwable $e) {
            Log::warning('GitLab approvals fetch failed', ['mr' => $pr->platform_pr_id, 'error' => $e->getMessage()]);
            return 0;
        }

        if (!$approvals) {
            return 0;
        }

        $approvedBy = $approvals['approved_by'] ?? [];
        $count      = 0;

        foreach ($approvedBy as $entry) {
            $user = $entry['user'] ?? [];
            Review::updateOrCreate(
                [
                    'pull_request_id'    => $pr->id,
                    'platform'           => 'gitlab',
                    'platform_review_id' => 'approval-' . ($user['id'] ?? 'unknown') . '-' . $mrIid,
                ],
                [
                    'reviewer_username' => $user['username'] ?? 'unknown',
                    'reviewer_avatar'   => $user['avatar_url'] ?? null,
                    'state'             => 'approved',
                    'body'              => '',
                    'submitted_at'      => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function syncIssues(GitLabService $service): int
    {
        $issues = $service->getIssues('opened', 'all');

        if (!$issues) {
            return 0;
        }

        $count = 0;
        foreach ($issues as $issue) {
            $labels = $issue['labels'] ?? [];

            Issue::updateOrCreate(
                [
                    'integration_id' => $this->integration->id,
                    'platform' => 'gitlab',
                    'platform_issue_id' => (string) $issue['id'],
                ],
                [
                    'repository' => $issue['references']['full'] ?? ($issue['web_url'] ?? ''),
                    'title' => $issue['title'],
                    'description' => $issue['description'] ?? '',
                    'state' => $issue['state'] === 'opened' ? 'open' : $issue['state'],
                    'type' => $this->determineIssueType($labels),
                    'priority' => $this->determinePriority($labels),
                    'author_username' => $issue['author']['username'] ?? 'unknown',
                    'author_avatar' => $issue['author']['avatar_url'] ?? null,
                    'assignees' => array_map(fn($a) => $a['username'], $issue['assignees'] ?? []),
                    'labels' => $labels,
                    'comments_count' => $issue['user_notes_count'] ?? 0,
                    'due_date' => isset($issue['due_date']) ? $issue['due_date'] : null,
                    'created_at_platform' => $issue['created_at'],
                    'updated_at_platform' => $issue['updated_at'],
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function mapMrState(string $state): string
    {
        return match ($state) {
            'opened' => 'open',
            'merged' => 'merged',
            'closed' => 'closed',
            default => $state,
        };
    }

    protected function determineIssueType(array $labels): string
    {
        $labelStr = strtolower(implode(' ', $labels));

        if (str_contains($labelStr, 'bug')) {
            return 'bug';
        } elseif (str_contains($labelStr, 'feature') || str_contains($labelStr, 'enhancement')) {
            return 'feature';
        } elseif (str_contains($labelStr, 'task') || str_contains($labelStr, 'chore')) {
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
