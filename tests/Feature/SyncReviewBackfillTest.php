<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Jobs\SyncGitLabData;
use App\Jobs\SyncBitbucketData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies that GitLab and Bitbucket sync jobs backfill approvals as Review rows
 * for existing PRs (parity with GitHub's reviewed-PR sync).
 */
class SyncReviewBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_gitlab_sync_backfills_approvals_as_reviews(): void
    {
        $user = User::create(['name' => 'GL', 'email' => 'gl-bf@bugradar.dev', 'email_verified_at' => now()]);
        $integration = Integration::create([
            'user_id' => $user->id, 'platform' => 'gitlab',
            'platform_user_id' => 'gl_1', 'username' => 'gluser',
            'access_token' => 'fake', 'is_active' => true,
        ]);

        // Two API endpoints get hit — /merge_requests then /projects/:id/merge_requests/:iid/approvals then /issues
        Http::fake([
            'gitlab.com/api/v4/merge_requests*' => Http::response([
                [
                    'id' => 1001, 'iid' => 5, 'project_id' => 50,
                    'title' => 'Fix auth', 'description' => 'body',
                    'state' => 'opened', 'source_branch' => 'fix', 'target_branch' => 'main',
                    'author' => ['username' => 'alice', 'avatar_url' => null],
                    'labels' => [], 'references' => ['full' => 'group/proj'],
                    'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
                ],
            ], 200),
            'gitlab.com/api/v4/projects/50/merge_requests/5/approvals' => Http::response([
                'approved_by' => [
                    ['user' => ['id' => 7, 'username' => 'reviewer1', 'avatar_url' => 'http://a']],
                    ['user' => ['id' => 8, 'username' => 'reviewer2', 'avatar_url' => 'http://b']],
                ],
            ], 200),
            'gitlab.com/api/v4/issues*' => Http::response([], 200),
        ]);

        (new SyncGitLabData($integration))->handle();

        // The MR was recorded
        $this->assertDatabaseHas('pull_requests', [
            'platform_pr_id' => '1001',
            'platform'       => 'gitlab',
        ]);

        // Both approvers became Review rows
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', [
            'platform'          => 'gitlab',
            'state'             => 'approved',
            'reviewer_username' => 'reviewer1',
        ]);
        $this->assertDatabaseHas('reviews', [
            'platform'          => 'gitlab',
            'state'             => 'approved',
            'reviewer_username' => 'reviewer2',
        ]);

        // SyncLog reflects the review count
        $this->assertDatabaseHas('sync_logs', [
            'integration_id' => $integration->id,
            'status'         => 'success',
            'prs_synced'     => 1,
            'reviews_synced' => 2,
        ]);
    }

    public function test_gitlab_sync_survives_missing_approvals_endpoint(): void
    {
        $user = User::create(['name' => 'GL', 'email' => 'gl-bf2@bugradar.dev', 'email_verified_at' => now()]);
        $integration = Integration::create([
            'user_id' => $user->id, 'platform' => 'gitlab',
            'platform_user_id' => 'gl_1', 'username' => 'gluser',
            'access_token' => 'fake', 'is_active' => true,
        ]);

        Http::fake([
            'gitlab.com/api/v4/merge_requests*' => Http::response([
                [
                    'id' => 2001, 'iid' => 5, 'project_id' => 50,
                    'title' => 'MR', 'state' => 'opened',
                    'source_branch' => 'x', 'target_branch' => 'main',
                    'author' => ['username' => 'alice'], 'labels' => [],
                    'references' => ['full' => 'group/proj'],
                    'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
                ],
            ], 200),
            // Approvals endpoint returns null-equivalent (service returns null on failure)
            'gitlab.com/api/v4/projects/50/merge_requests/5/approvals' => Http::response(null, 403),
            'gitlab.com/api/v4/issues*' => Http::response([], 200),
        ]);

        // Should NOT throw
        (new SyncGitLabData($integration))->handle();

        $this->assertDatabaseHas('pull_requests', ['platform_pr_id' => '2001']);
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseHas('sync_logs', [
            'status'         => 'success',
            'reviews_synced' => 0,
        ]);
    }

    public function test_bitbucket_sync_backfills_approvals_from_activity(): void
    {
        $user = User::create(['name' => 'BB', 'email' => 'bb-bf@bugradar.dev', 'email_verified_at' => now()]);
        $integration = Integration::create([
            'user_id' => $user->id, 'platform' => 'bitbucket',
            'platform_user_id' => 'bb_1', 'username' => 'bbuser',
            'access_token' => 'fake', 'is_active' => true,
        ]);

        // Bitbucket service call order in the sync job:
        //  1) getUser()                                           → to get uuid
        //  2) getPullRequests(OPEN)  = /pullrequests/{uuid}       → list of PRs
        //  3) getPullRequestActivity(ws, slug, pr_id)             → approvals
        //  4) getRepositories(),  getIssues(...) per repo         → issue sync
        //
        // Pattern order matters — first match wins in Http::fake. Put most
        // specific paths first so the general `repositories*` catch-all doesn't
        // swallow the activity request.
        Http::fake([
            'api.bitbucket.org/2.0/repositories/*/*/pullrequests/*/activity*' => Http::response([
                'values' => [
                    ['comment' => ['id' => 1, 'content' => ['raw' => 'nit']]],
                    ['approval' => [
                        'date' => '2026-07-09T10:00:00Z',
                        'user' => [
                            'uuid' => '{reviewer-1}', 'display_name' => 'Reviewer One',
                            'links' => ['avatar' => ['href' => 'http://a']],
                        ],
                    ]],
                    ['approval' => [
                        'date' => '2026-07-09T10:05:00Z',
                        'user' => [
                            'uuid' => '{reviewer-2}', 'display_name' => 'Reviewer Two',
                            'links' => ['avatar' => ['href' => 'http://b']],
                        ],
                    ]],
                ],
            ], 200),
            'api.bitbucket.org/2.0/user' => Http::response(['uuid' => '{user-uuid}'], 200),
            'api.bitbucket.org/2.0/pullrequests/*' => Http::response([
                'values' => [[
                    'id' => 42, 'title' => 'Add x', 'description' => '', 'state' => 'OPEN',
                    'author' => ['display_name' => 'alice', 'links' => ['avatar' => ['href' => 'http://x']]],
                    'source' => ['branch' => ['name' => 'feat']],
                    'destination' => [
                        'branch' => ['name' => 'main'],
                        'repository' => ['full_name' => 'ws/repo'],
                    ],
                    'comment_count' => 3,
                    'created_on' => '2026-07-09T09:00:00Z',
                    'updated_on' => '2026-07-09T10:05:00Z',
                ]],
            ], 200),
            // No repos = no issue sync work — this is the general catch-all, listed LAST
            'api.bitbucket.org/2.0/repositories*' => Http::response(['values' => []], 200),
        ]);

        (new SyncBitbucketData($integration))->handle();

        $this->assertDatabaseHas('pull_requests', [
            'platform_pr_id' => '42',
            'platform'       => 'bitbucket',
            'state'          => 'open',
        ]);
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', [
            'platform' => 'bitbucket', 'state' => 'approved',
            'reviewer_username' => 'Reviewer One',
        ]);
        $this->assertDatabaseHas('reviews', [
            'platform' => 'bitbucket', 'state' => 'approved',
            'reviewer_username' => 'Reviewer Two',
        ]);
        $this->assertDatabaseHas('sync_logs', [
            'integration_id' => $integration->id,
            'status'         => 'success',
            'prs_synced'     => 1,
            'reviews_synced' => 2,
        ]);
    }
}
