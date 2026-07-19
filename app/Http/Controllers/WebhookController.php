<?php

namespace App\Http\Controllers;

use App\Models\TrackedRepository;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Models\Review;
use App\Services\GitHubDataMapper;
use App\Services\GitLabDataMapper;
use App\Services\BitbucketDataMapper;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    /**
     * POST /api/webhooks/github/{trackedRepository}
     *
     * Receives GitHub webhook deliveries for a specific tracked repository.
     * Verifies the HMAC signature, upserts the event data, and notifies the user.
     */
    public function github(Request $request, TrackedRepository $trackedRepository): JsonResponse
    {
        // 1. Verify the signature using this repo's stored secret
        if (!$this->verifyGithubSignature($request, $trackedRepository)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event   = $request->header('X-GitHub-Event');
        $payload = $request->json()->all();

        try {
            match ($event) {
                'ping'                 => null, // GitHub's test delivery
                'pull_request'         => $this->handlePullRequest($trackedRepository, $payload),
                'issues'               => $this->handleIssue($trackedRepository, $payload),
                'pull_request_review'  => $this->handleReview($trackedRepository, $payload),
                default                => Log::info('Unhandled GitHub webhook event', ['event' => $event]),
            };
        } catch (\Exception $e) {
            Log::error('GitHub webhook processing failed', [
                'event' => $event,
                'repo'  => $trackedRepository->repo_full_name,
                'error' => $e->getMessage(),
            ]);
            // Return 200 so GitHub doesn't retry a payload we can't process
            return response()->json(['message' => 'Received (processing error logged)'], 200);
        }

        return response()->json(['message' => 'ok'], 200);
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    private function handlePullRequest(TrackedRepository $tracked, array $payload): void
    {
        $pr     = $payload['pull_request'] ?? null;
        $action = $payload['action'] ?? '';
        if (!$pr) {
            return;
        }

        $state = $pr['state'] ?? 'open';
        if (($pr['merged'] ?? false) === true) {
            $state = 'merged';
        }

        $pullRequest = PullRequest::updateOrCreate(
            [
                'integration_id'  => $tracked->integration_id,
                'platform'        => 'github',
                'platform_pr_id'  => (string) $pr['id'],
            ],
            [
                'repository'           => $tracked->repo_full_name,
                'title'                => $pr['title'] ?? '',
                'description'          => $pr['body'] ?? '',
                'state'                => $state,
                'author_username'      => $pr['user']['login'] ?? 'unknown',
                'author_avatar'        => $pr['user']['avatar_url'] ?? null,
                'branch_from'          => $pr['head']['ref'] ?? null,
                'branch_to'            => $pr['base']['ref'] ?? null,
                'comments_count'       => $pr['comments'] ?? 0,
                'labels'               => array_column($pr['labels'] ?? [], 'name'),
                'created_at_platform'  => $pr['created_at'] ?? null,
                'updated_at_platform'  => $pr['updated_at'] ?? null,
                'merged_at'            => $pr['merged_at'] ?? null,
            ]
        );

        // Notify on meaningful actions only
        if (in_array($action, ['opened', 'reopened', 'ready_for_review'], true)) {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'New Pull Request',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'pull_request', 'id' => (string) $pullRequest->id, 'platform' => 'github']
            );
        } elseif ($action === 'closed' && $state === 'merged') {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'Pull Request Merged',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'pull_request', 'id' => (string) $pullRequest->id, 'platform' => 'github']
            );
        }
    }

    private function handleIssue(TrackedRepository $tracked, array $payload): void
    {
        $issue  = $payload['issue'] ?? null;
        $action = $payload['action'] ?? '';
        if (!$issue) {
            return;
        }

        $labels = $issue['labels'] ?? [];

        $record = Issue::updateOrCreate(
            [
                'integration_id'    => $tracked->integration_id,
                'platform'          => 'github',
                'platform_issue_id' => (string) $issue['id'],
            ],
            [
                'repository'          => $tracked->repo_full_name,
                'title'               => $issue['title'] ?? '',
                'description'         => $issue['body'] ?? '',
                'state'               => $issue['state'] ?? 'open',
                'type'                => GitHubDataMapper::determineIssueType($labels),
                'priority'            => GitHubDataMapper::determinePriority($labels),
                'author_username'     => $issue['user']['login'] ?? 'unknown',
                'author_avatar'       => $issue['user']['avatar_url'] ?? null,
                'assignees'           => array_column($issue['assignees'] ?? [], 'login'),
                'labels'              => array_column($labels, 'name'),
                'comments_count'      => $issue['comments'] ?? 0,
                'created_at_platform' => $issue['created_at'] ?? null,
                'updated_at_platform' => $issue['updated_at'] ?? null,
                'closed_at'           => $issue['closed_at'] ?? null,
            ]
        );

        if (in_array($action, ['opened', 'reopened', 'assigned'], true)) {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                $record->type === 'bug' ? 'New Bug Reported' : 'New Issue',
                "{$tracked->repo_full_name}: {$record->title}",
                ['type' => 'issue', 'id' => (string) $record->id, 'platform' => 'github']
            );
        }
    }

    private function handleReview(TrackedRepository $tracked, array $payload): void
    {
        $review = $payload['review'] ?? null;
        $pr     = $payload['pull_request'] ?? null;
        if (!$review || !$pr) {
            return;
        }

        // Make sure the parent PR exists locally
        $pullRequest = PullRequest::updateOrCreate(
            [
                'integration_id' => $tracked->integration_id,
                'platform'       => 'github',
                'platform_pr_id' => (string) $pr['id'],
            ],
            [
                'repository'          => $tracked->repo_full_name,
                'title'               => $pr['title'] ?? '',
                'state'               => $pr['state'] ?? 'open',
                'author_username'     => $pr['user']['login'] ?? 'unknown',
                'author_avatar'       => $pr['user']['avatar_url'] ?? null,
                'created_at_platform' => $pr['created_at'] ?? null,
                'updated_at_platform' => $pr['updated_at'] ?? null,
            ]
        );

        $record = Review::updateOrCreate(
            [
                'pull_request_id'    => $pullRequest->id,
                'platform'           => 'github',
                'platform_review_id' => (string) $review['id'],
            ],
            [
                'reviewer_username' => $review['user']['login'] ?? 'unknown',
                'reviewer_avatar'   => $review['user']['avatar_url'] ?? null,
                'state'             => strtolower($review['state'] ?? 'commented'),
                'body'              => $review['body'] ?? '',
                'submitted_at'      => $review['submitted_at'] ?? null,
            ]
        );

        $this->notifications->notifyUser(
            $tracked->integration->user,
            'New Review on your PR',
            "{$tracked->repo_full_name}: {$pullRequest->title} — {$record->state}",
            ['type' => 'review', 'id' => (string) $record->id, 'platform' => 'github']
        );
    }

    // -------------------------------------------------------------------------
    // Signature verification
    // -------------------------------------------------------------------------

    private function verifyGithubSignature(Request $request, TrackedRepository $tracked): bool
    {
        $secret = $tracked->webhook_secret;
        if (!$secret) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return is_string($signature) && hash_equals($expected, $signature);
    }

    // =========================================================================
    // GitLab
    // =========================================================================

    /**
     * POST /api/webhooks/gitlab/{trackedRepository}
     * GitLab sends X-Gitlab-Token (plaintext) — compare with stored secret.
     */
    public function gitlab(Request $request, TrackedRepository $trackedRepository): JsonResponse
    {
        $token = $request->header('X-Gitlab-Token', '');
        if (!$trackedRepository->webhook_secret || !hash_equals($trackedRepository->webhook_secret, (string) $token)) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $event   = $request->header('X-Gitlab-Event');
        $kind    = $request->json('object_kind');
        $payload = $request->json()->all();

        try {
            match ($kind) {
                'merge_request' => $this->handleGitlabMergeRequest($trackedRepository, $payload),
                'issue'         => $this->handleGitlabIssue($trackedRepository, $payload),
                'note'          => $this->handleGitlabNote($trackedRepository, $payload),
                default         => Log::info('Unhandled GitLab webhook', ['event' => $event, 'kind' => $kind]),
            };
        } catch (\Exception $e) {
            Log::error('GitLab webhook processing failed', [
                'kind'  => $kind,
                'repo'  => $trackedRepository->repo_full_name,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Received (processing error logged)'], 200);
        }

        return response()->json(['message' => 'ok'], 200);
    }

    private function handleGitlabMergeRequest(TrackedRepository $tracked, array $payload): void
    {
        $attrs = $payload['object_attributes'] ?? null;
        if (!$attrs) {
            return;
        }

        $action  = $attrs['action'] ?? '';
        $isMerged = ($attrs['state'] ?? '') === 'merged' || $action === 'merge';
        $state    = GitLabDataMapper::mapMrState($attrs['state'] ?? 'opened', $isMerged);

        $pullRequest = PullRequest::updateOrCreate(
            [
                'integration_id' => $tracked->integration_id,
                'platform'       => 'gitlab',
                'platform_pr_id' => (string) ($attrs['id'] ?? ''),
            ],
            [
                'repository'          => $tracked->repo_full_name,
                'title'               => $attrs['title'] ?? '',
                'description'         => $attrs['description'] ?? '',
                'state'               => $state,
                'author_username'     => $payload['user']['username'] ?? 'unknown',
                'author_avatar'       => $payload['user']['avatar_url'] ?? null,
                'branch_from'         => $attrs['source_branch'] ?? null,
                'branch_to'           => $attrs['target_branch'] ?? null,
                'labels'              => GitLabDataMapper::labelStrings($payload['labels'] ?? []),
                'created_at_platform' => $attrs['created_at'] ?? null,
                'updated_at_platform' => $attrs['updated_at'] ?? null,
                'merged_at'           => $isMerged ? ($attrs['updated_at'] ?? now()) : null,
            ]
        );

        if (in_array($action, ['open', 'reopen'], true)) {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'New Merge Request',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'pull_request', 'id' => (string) $pullRequest->id, 'platform' => 'gitlab']
            );
        } elseif ($action === 'merge' || $isMerged) {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'Merge Request Merged',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'pull_request', 'id' => (string) $pullRequest->id, 'platform' => 'gitlab']
            );
        } elseif ($action === 'approved') {
            // Persist an approval as a Review row
            Review::updateOrCreate(
                [
                    'pull_request_id'    => $pullRequest->id,
                    'platform'           => 'gitlab',
                    'platform_review_id' => 'approval-' . ($payload['user']['id'] ?? 'unknown') . '-' . ($attrs['id'] ?? ''),
                ],
                [
                    'reviewer_username' => $payload['user']['username'] ?? 'unknown',
                    'reviewer_avatar'   => $payload['user']['avatar_url'] ?? null,
                    'state'             => 'approved',
                    'body'              => '',
                    'submitted_at'      => now(),
                ]
            );
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'MR Approved',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'review', 'id' => (string) $pullRequest->id, 'platform' => 'gitlab']
            );
        }
    }

    private function handleGitlabIssue(TrackedRepository $tracked, array $payload): void
    {
        $attrs = $payload['object_attributes'] ?? null;
        if (!$attrs) {
            return;
        }

        $labels = $payload['labels'] ?? [];
        $action = $attrs['action'] ?? '';

        $record = Issue::updateOrCreate(
            [
                'integration_id'    => $tracked->integration_id,
                'platform'          => 'gitlab',
                'platform_issue_id' => (string) ($attrs['id'] ?? ''),
            ],
            [
                'repository'          => $tracked->repo_full_name,
                'title'               => $attrs['title'] ?? '',
                'description'         => $attrs['description'] ?? '',
                'state'               => GitLabDataMapper::mapIssueState($attrs['state'] ?? 'opened'),
                'type'                => GitLabDataMapper::determineIssueType($labels),
                'priority'            => GitLabDataMapper::determinePriority($labels),
                'author_username'     => $payload['user']['username'] ?? 'unknown',
                'author_avatar'       => $payload['user']['avatar_url'] ?? null,
                'assignees'           => array_column($payload['assignees'] ?? [], 'username'),
                'labels'              => GitLabDataMapper::labelStrings($labels),
                'comments_count'      => (int) ($attrs['user_notes_count'] ?? 0),
                'created_at_platform' => $attrs['created_at'] ?? null,
                'updated_at_platform' => $attrs['updated_at'] ?? null,
                'closed_at'           => $attrs['closed_at'] ?? null,
            ]
        );

        if (in_array($action, ['open', 'reopen'], true)) {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                $record->type === 'bug' ? 'New Bug Reported' : 'New Issue',
                "{$tracked->repo_full_name}: {$record->title}",
                ['type' => 'issue', 'id' => (string) $record->id, 'platform' => 'gitlab']
            );
        }
    }

    /**
     * Note (comment) events. We only surface notes on MRs as review activity for now.
     */
    private function handleGitlabNote(TrackedRepository $tracked, array $payload): void
    {
        $noteableType = $payload['object_attributes']['noteable_type'] ?? '';
        if ($noteableType !== 'MergeRequest') {
            return;
        }

        $mr = $payload['merge_request'] ?? null;
        if (!$mr) {
            return;
        }

        // Make sure the parent PR exists locally
        $pullRequest = PullRequest::firstWhere([
            'integration_id' => $tracked->integration_id,
            'platform'       => 'gitlab',
            'platform_pr_id' => (string) ($mr['id'] ?? ''),
        ]);
        if (!$pullRequest) {
            return;
        }

        $note = $payload['object_attributes'];
        Review::updateOrCreate(
            [
                'pull_request_id'    => $pullRequest->id,
                'platform'           => 'gitlab',
                'platform_review_id' => 'note-' . ($note['id'] ?? ''),
            ],
            [
                'reviewer_username' => $payload['user']['username'] ?? 'unknown',
                'reviewer_avatar'   => $payload['user']['avatar_url'] ?? null,
                'state'             => 'commented',
                'body'              => $note['note'] ?? '',
                'submitted_at'      => $note['created_at'] ?? now(),
            ]
        );

        $this->notifications->notifyUser(
            $tracked->integration->user,
            'New comment on your MR',
            "{$tracked->repo_full_name}: {$pullRequest->title}",
            ['type' => 'review', 'id' => (string) $pullRequest->id, 'platform' => 'gitlab']
        );
    }

    // =========================================================================
    // Bitbucket
    // =========================================================================

    /**
     * POST /api/webhooks/bitbucket/{trackedRepository}
     * Bitbucket sends X-Hub-Signature: sha256=<hmac(body, secret)> — same format as GitHub.
     */
    public function bitbucket(Request $request, TrackedRepository $trackedRepository): JsonResponse
    {
        if (!$this->verifyBitbucketSignature($request, $trackedRepository)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event   = $request->header('X-Event-Key', '');
        $payload = $request->json()->all();

        try {
            match (true) {
                str_starts_with($event, 'pullrequest:') => $this->handleBitbucketPullRequest($trackedRepository, $event, $payload),
                str_starts_with($event, 'issue:')       => $this->handleBitbucketIssue($trackedRepository, $event, $payload),
                default                                 => Log::info('Unhandled Bitbucket webhook event', ['event' => $event]),
            };
        } catch (\Exception $e) {
            Log::error('Bitbucket webhook processing failed', [
                'event' => $event,
                'repo'  => $trackedRepository->repo_full_name,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Received (processing error logged)'], 200);
        }

        return response()->json(['message' => 'ok'], 200);
    }

    private function handleBitbucketPullRequest(TrackedRepository $tracked, string $event, array $payload): void
    {
        $pr    = $payload['pullrequest'] ?? null;
        $actor = $payload['actor'] ?? [];
        if (!$pr) {
            return;
        }

        $pullRequest = PullRequest::updateOrCreate(
            [
                'integration_id' => $tracked->integration_id,
                'platform'       => 'bitbucket',
                'platform_pr_id' => (string) ($pr['id'] ?? ''),
            ],
            [
                'repository'          => $tracked->repo_full_name,
                'title'               => $pr['title'] ?? '',
                'description'         => $pr['description'] ?? '',
                'state'               => BitbucketDataMapper::mapPrState($pr['state'] ?? 'OPEN'),
                'author_username'     => $pr['author']['display_name'] ?? 'unknown',
                'author_avatar'       => $pr['author']['links']['avatar']['href'] ?? null,
                'branch_from'         => $pr['source']['branch']['name'] ?? null,
                'branch_to'           => $pr['destination']['branch']['name'] ?? null,
                'comments_count'      => (int) ($pr['comment_count'] ?? 0),
                'labels'              => [],
                'created_at_platform' => $pr['created_on'] ?? null,
                'updated_at_platform' => $pr['updated_on'] ?? null,
                'merged_at'           => $event === 'pullrequest:fulfilled' ? ($pr['updated_on'] ?? now()) : null,
            ]
        );

        // Fire notifications on meaningful transitions
        if ($event === 'pullrequest:created') {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'New Pull Request',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'pull_request', 'id' => (string) $pullRequest->id, 'platform' => 'bitbucket']
            );
        } elseif ($event === 'pullrequest:fulfilled') {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'Pull Request Merged',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'pull_request', 'id' => (string) $pullRequest->id, 'platform' => 'bitbucket']
            );
        } elseif ($event === 'pullrequest:approved') {
            $reviewerId = $actor['uuid'] ?? $actor['account_id'] ?? 'unknown';
            Review::updateOrCreate(
                [
                    'pull_request_id'    => $pullRequest->id,
                    'platform'           => 'bitbucket',
                    'platform_review_id' => 'approval-' . trim($reviewerId, '{}') . '-' . ($pr['id'] ?? ''),
                ],
                [
                    'reviewer_username' => $actor['display_name'] ?? 'unknown',
                    'reviewer_avatar'   => $actor['links']['avatar']['href'] ?? null,
                    'state'             => 'approved',
                    'body'              => '',
                    'submitted_at'      => now(),
                ]
            );
            $this->notifications->notifyUser(
                $tracked->integration->user,
                'PR Approved',
                "{$tracked->repo_full_name}: {$pullRequest->title}",
                ['type' => 'review', 'id' => (string) $pullRequest->id, 'platform' => 'bitbucket']
            );
        }
    }

    private function handleBitbucketIssue(TrackedRepository $tracked, string $event, array $payload): void
    {
        $issue = $payload['issue'] ?? null;
        if (!$issue) {
            return;
        }

        $record = Issue::updateOrCreate(
            [
                'integration_id'    => $tracked->integration_id,
                'platform'          => 'bitbucket',
                'platform_issue_id' => (string) ($issue['id'] ?? ''),
            ],
            [
                'repository'          => $tracked->repo_full_name,
                'title'               => $issue['title'] ?? '',
                'description'         => $issue['content']['raw'] ?? '',
                'state'               => BitbucketDataMapper::mapIssueState($issue['state'] ?? 'new'),
                'type'                => BitbucketDataMapper::mapIssueType($issue['kind'] ?? 'task'),
                'priority'            => BitbucketDataMapper::mapPriority($issue['priority'] ?? 'major'),
                'author_username'     => $issue['reporter']['display_name'] ?? 'unknown',
                'author_avatar'       => $issue['reporter']['links']['avatar']['href'] ?? null,
                'assignees'           => isset($issue['assignee']) ? [$issue['assignee']['display_name']] : [],
                'labels'              => isset($issue['component']) ? [$issue['component']['name']] : [],
                'comments_count'      => (int) ($issue['comment_count'] ?? 0),
                'created_at_platform' => $issue['created_on'] ?? null,
                'updated_at_platform' => $issue['updated_on'] ?? null,
            ]
        );

        if ($event === 'issue:created') {
            $this->notifications->notifyUser(
                $tracked->integration->user,
                $record->type === 'bug' ? 'New Bug Reported' : 'New Issue',
                "{$tracked->repo_full_name}: {$record->title}",
                ['type' => 'issue', 'id' => (string) $record->id, 'platform' => 'bitbucket']
            );
        }
    }

    private function verifyBitbucketSignature(Request $request, TrackedRepository $tracked): bool
    {
        $secret = $tracked->webhook_secret;
        if (!$secret) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature', '');
        $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return is_string($signature) && hash_equals($expected, $signature);
    }
}
