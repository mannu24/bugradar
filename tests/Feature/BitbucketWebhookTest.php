<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Models\TrackedRepository;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BitbucketWebhookTest extends TestCase
{
    use RefreshDatabase;

    private TrackedRepository $tracked;
    private string $secret = 'bitbucket-secret-abc';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'BB Tester', 'email' => 'bb@bugradar.dev', 'email_verified_at' => now(),
        ]);

        $integration = Integration::create([
            'user_id' => $user->id, 'platform' => 'bitbucket',
            'platform_user_id' => 'bb_1', 'username' => 'bbtester',
            'access_token' => 'fake', 'is_active' => true,
        ]);

        $this->tracked = TrackedRepository::create([
            'integration_id' => $integration->id,
            'platform'       => 'bitbucket',
            'repo_full_name' => 'bbtester/repo',
            'is_active'      => true,
            'webhook_id'     => 'bb-hook-1',
            'webhook_secret' => $this->secret,
            'webhook_active' => true,
        ]);
    }

    private function sendWebhook(array $payload, string $event, ?string $signature = null)
    {
        $body = json_encode($payload);
        $sig  = $signature ?? ('sha256=' . hash_hmac('sha256', $body, $this->secret));

        return $this->call(
            'POST',
            "/api/webhooks/bitbucket/{$this->tracked->id}",
            [], [], [],
            [
                'HTTP_X_EVENT_KEY'      => $event,
                'HTTP_X_HUB_SIGNATURE'  => $sig,
                'CONTENT_TYPE'          => 'application/json',
            ],
            $body
        );
    }

    public function test_rejects_invalid_signature(): void
    {
        $res = $this->sendWebhook(['pullrequest' => ['id' => 1]], 'pullrequest:created', 'sha256=wrong');
        $res->assertStatus(401);
    }

    public function test_pullrequest_created(): void
    {
        $payload = [
            'pullrequest' => [
                'id' => 42, 'title' => 'Add thing', 'description' => 'desc',
                'state' => 'OPEN',
                'author' => ['display_name' => 'alice', 'links' => ['avatar' => ['href' => 'http://a']]],
                'source' => ['branch' => ['name' => 'feature']],
                'destination' => ['branch' => ['name' => 'main']],
                'comment_count' => 0,
                'created_on' => '2026-07-09T10:00:00Z', 'updated_on' => '2026-07-09T10:00:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'pullrequest:created')->assertOk();

        $this->assertDatabaseHas('pull_requests', [
            'platform_pr_id' => '42',
            'platform'       => 'bitbucket',
            'state'          => 'open',
            'branch_from'    => 'feature',
        ]);
    }

    public function test_pullrequest_fulfilled_marks_merged(): void
    {
        $payload = [
            'pullrequest' => [
                'id' => 43, 'title' => 'Merged one', 'state' => 'MERGED',
                'author' => ['display_name' => 'alice'],
                'source' => ['branch' => ['name' => 'x']],
                'destination' => ['branch' => ['name' => 'main']],
                'created_on' => '2026-07-09T10:00:00Z', 'updated_on' => '2026-07-09T10:05:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'pullrequest:fulfilled')->assertOk();

        $this->assertDatabaseHas('pull_requests', [
            'platform_pr_id' => '43',
            'state'          => 'merged',
        ]);
    }

    public function test_pullrequest_approved_creates_review(): void
    {
        $payload = [
            'actor' => [
                'uuid' => '{aaa-bbb}', 'display_name' => 'Reviewer',
                'links' => ['avatar' => ['href' => 'http://a']],
            ],
            'pullrequest' => [
                'id' => 44, 'title' => 'To approve', 'state' => 'OPEN',
                'author' => ['display_name' => 'alice'],
                'source' => ['branch' => ['name' => 'x']],
                'destination' => ['branch' => ['name' => 'main']],
                'created_on' => '2026-07-09T10:00:00Z', 'updated_on' => '2026-07-09T10:00:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'pullrequest:approved')->assertOk();

        $this->assertDatabaseHas('reviews', [
            'platform' => 'bitbucket',
            'state'    => 'approved',
            'reviewer_username' => 'Reviewer',
        ]);
    }

    public function test_issue_created_with_mapped_kind_and_priority(): void
    {
        $payload = [
            'issue' => [
                'id' => 100, 'title' => 'Broken UI', 'kind' => 'bug', 'priority' => 'critical',
                'state' => 'new', 'content' => ['raw' => 'Steps to repro'],
                'reporter' => ['display_name' => 'bob'],
                'created_on' => '2026-07-09T10:00:00Z', 'updated_on' => '2026-07-09T10:00:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'issue:created')->assertOk();

        $this->assertDatabaseHas('issues', [
            'platform_issue_id' => '100',
            'platform' => 'bitbucket',
            'type'     => 'bug',
            'priority' => 'critical',
            'state'    => 'open',
        ]);
    }

    public function test_notifies_device(): void
    {
        DeviceToken::create([
            'user_id' => $this->tracked->integration->user_id,
            'token'   => 'device-bb',
        ]);

        $spy = $this->spy(FcmService::class);

        $payload = [
            'issue' => [
                'id' => 101, 'title' => 'Ping', 'kind' => 'task', 'priority' => 'minor',
                'state' => 'new', 'content' => ['raw' => ''],
                'reporter' => ['display_name' => 'bob'],
                'created_on' => '2026-07-09T10:00:00Z', 'updated_on' => '2026-07-09T10:00:00Z',
            ],
        ];

        $this->sendWebhook($payload, 'issue:created')->assertOk();

        $spy->shouldHaveReceived('send')
            ->withArgs(fn($token) => $token === 'device-bb')
            ->once();
    }
}
