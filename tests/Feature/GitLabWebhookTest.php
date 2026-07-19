<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Models\TrackedRepository;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitLabWebhookTest extends TestCase
{
    use RefreshDatabase;

    private TrackedRepository $tracked;
    private string $secret = 'gitlab-token-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'GL Tester', 'email' => 'gl@bugradar.dev', 'email_verified_at' => now(),
        ]);

        $integration = Integration::create([
            'user_id' => $user->id, 'platform' => 'gitlab',
            'platform_user_id' => 'gl_1', 'username' => 'gltester',
            'access_token' => 'fake', 'is_active' => true,
        ]);

        $this->tracked = TrackedRepository::create([
            'integration_id' => $integration->id,
            'platform'       => 'gitlab',
            'repo_full_name' => 'gltester/proj',
            'repo_platform_id' => '99',
            'is_active'      => true,
            'webhook_id'     => 'gl-hook-1',
            'webhook_secret' => $this->secret,
            'webhook_active' => true,
        ]);
    }

    private function sendWebhook(array $payload, string $event, ?string $token = null)
    {
        return $this->call(
            'POST',
            "/api/webhooks/gitlab/{$this->tracked->id}",
            [], [], [],
            [
                'HTTP_X_GITLAB_EVENT' => $event,
                'HTTP_X_GITLAB_TOKEN' => $token ?? $this->secret,
                'CONTENT_TYPE'        => 'application/json',
            ],
            json_encode($payload)
        );
    }

    public function test_rejects_invalid_token(): void
    {
        $res = $this->sendWebhook(['object_kind' => 'merge_request'], 'Merge Request Hook', 'wrong-token');
        $res->assertStatus(401);
    }

    public function test_merge_request_open_creates_pr(): void
    {
        $payload = [
            'object_kind' => 'merge_request',
            'user' => ['username' => 'alice', 'avatar_url' => 'http://a/av.png'],
            'labels' => [['title' => 'feature']],
            'object_attributes' => [
                'id' => 555, 'iid' => 3, 'title' => 'Add feature X', 'description' => 'body',
                'state' => 'opened', 'action' => 'open',
                'source_branch' => 'feat-x', 'target_branch' => 'main',
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
            ],
        ];

        $res = $this->sendWebhook($payload, 'Merge Request Hook');
        $res->assertOk();

        $this->assertDatabaseHas('pull_requests', [
            'integration_id' => $this->tracked->integration_id,
            'platform'       => 'gitlab',
            'platform_pr_id' => '555',
            'state'          => 'open',
            'branch_from'    => 'feat-x',
        ]);
    }

    public function test_merge_request_merged(): void
    {
        $payload = [
            'object_kind' => 'merge_request',
            'user' => ['username' => 'alice'],
            'labels' => [],
            'object_attributes' => [
                'id' => 556, 'title' => 'Merged one', 'state' => 'merged', 'action' => 'merge',
                'source_branch' => 'x', 'target_branch' => 'main',
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:05:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'Merge Request Hook')->assertOk();

        $this->assertDatabaseHas('pull_requests', [
            'platform_pr_id' => '556',
            'state'          => 'merged',
        ]);
    }

    public function test_issue_creates_with_mapped_type_and_priority(): void
    {
        $payload = [
            'object_kind' => 'issue',
            'user' => ['username' => 'bob'],
            'labels' => [['title' => 'bug'], ['title' => 'critical']],
            'assignees' => [['username' => 'gltester']],
            'object_attributes' => [
                'id' => 777, 'title' => 'App crashes', 'description' => 'trace',
                'state' => 'opened', 'action' => 'open', 'user_notes_count' => 2,
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
                'closed_at' => null,
            ],
        ];

        $this->sendWebhook($payload, 'Issue Hook')->assertOk();

        $this->assertDatabaseHas('issues', [
            'platform_issue_id' => '777',
            'state'    => 'open',
            'type'     => 'bug',
            'priority' => 'critical',
        ]);
    }

    public function test_approval_creates_review(): void
    {
        // seed a PR first
        \App\Models\PullRequest::create([
            'integration_id' => $this->tracked->integration_id,
            'platform'       => 'gitlab',
            'platform_pr_id' => '888',
            'repository'     => $this->tracked->repo_full_name,
            'title'          => 'PR to approve',
            'state'          => 'open',
            'author_username'=> 'alice',
        ]);

        $payload = [
            'object_kind' => 'merge_request',
            'user' => ['id' => 12, 'username' => 'reviewer', 'avatar_url' => null],
            'labels' => [],
            'object_attributes' => [
                'id' => 888, 'title' => 'PR to approve', 'state' => 'opened', 'action' => 'approved',
                'source_branch' => 'x', 'target_branch' => 'main',
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:05:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'Merge Request Hook')->assertOk();

        $this->assertDatabaseHas('reviews', [
            'platform' => 'gitlab',
            'state'    => 'approved',
            'reviewer_username' => 'reviewer',
        ]);
    }

    public function test_notifies_device(): void
    {
        DeviceToken::create([
            'user_id' => $this->tracked->integration->user_id,
            'token'   => 'device-gl',
        ]);

        $spy = $this->spy(FcmService::class);

        $payload = [
            'object_kind' => 'issue',
            'user' => ['username' => 'bob'],
            'labels' => [], 'assignees' => [],
            'object_attributes' => [
                'id' => 999, 'title' => 'Ping', 'state' => 'opened', 'action' => 'open',
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'Issue Hook')->assertOk();

        $spy->shouldHaveReceived('send')
            ->withArgs(fn($token) => $token === 'device-gl')
            ->once();
    }
}
