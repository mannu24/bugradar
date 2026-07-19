<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Models\TrackedRepository;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private TrackedRepository $tracked;
    private string $secret = 'test-webhook-secret-123';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Hook Tester', 'email' => 'hook@bugradar.dev', 'email_verified_at' => now(),
        ]);

        $integration = Integration::create([
            'user_id' => $user->id, 'platform' => 'github',
            'platform_user_id' => 'gh_1', 'username' => 'hooktester',
            'access_token' => 'fake', 'is_active' => true,
        ]);

        $this->tracked = TrackedRepository::create([
            'integration_id' => $integration->id,
            'platform'       => 'github',
            'repo_full_name' => 'hooktester/repo',
            'is_active'      => true,
            'webhook_id'     => '999',
            'webhook_secret' => $this->secret,
            'webhook_active' => true,
        ]);

        // FCM not configured in tests → notifications are logged, not sent
    }

    private function signed(array $payload, string $event): array
    {
        $body      = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->secret);

        return [
            'body'    => $body,
            'headers' => [
                'X-GitHub-Event'        => $event,
                'X-Hub-Signature-256'   => $signature,
                'Content-Type'          => 'application/json',
            ],
        ];
    }

    private function sendWebhook(array $payload, string $event)
    {
        $s = $this->signed($payload, $event);
        return $this->call(
            'POST',
            "/api/webhooks/github/{$this->tracked->id}",
            [], [], [],
            $this->transformHeaders($s['headers']),
            $s['body']
        );
    }

    private function transformHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $k => $v) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
        return $server;
    }

    public function test_rejects_invalid_signature(): void
    {
        $res = $this->call(
            'POST',
            "/api/webhooks/github/{$this->tracked->id}",
            [], [], [],
            $this->transformHeaders([
                'X-GitHub-Event'      => 'ping',
                'X-Hub-Signature-256' => 'sha256=wrong',
                'Content-Type'        => 'application/json',
            ]),
            json_encode(['zen' => 'hi'])
        );

        $res->assertStatus(401);
    }

    public function test_ping_event_ok(): void
    {
        $res = $this->sendWebhook(['zen' => 'Keep it simple'], 'ping');
        $res->assertOk();
    }

    public function test_pull_request_opened_creates_pr(): void
    {
        $payload = [
            'action' => 'opened',
            'pull_request' => [
                'id' => 555, 'number' => 12, 'title' => 'Add feature X', 'body' => 'desc',
                'state' => 'open', 'merged' => false,
                'user' => ['login' => 'alice', 'avatar_url' => 'http://a/av.png'],
                'head' => ['ref' => 'feature-x'], 'base' => ['ref' => 'main'],
                'comments' => 0, 'labels' => [['name' => 'feature']],
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
                'merged_at' => null,
            ],
            'repository' => ['full_name' => 'hooktester/repo'],
        ];

        $res = $this->sendWebhook($payload, 'pull_request');
        $res->assertOk();

        $this->assertDatabaseHas('pull_requests', [
            'integration_id' => $this->tracked->integration_id,
            'platform_pr_id' => '555',
            'title'          => 'Add feature X',
            'state'          => 'open',
        ]);
    }

    public function test_issue_opened_creates_issue_with_mapped_type(): void
    {
        $payload = [
            'action' => 'opened',
            'issue' => [
                'id' => 777, 'number' => 3, 'title' => 'App crashes', 'body' => 'stack trace',
                'state' => 'open',
                'user' => ['login' => 'bob', 'avatar_url' => null],
                'assignees' => [['login' => 'hooktester']],
                'labels' => [['name' => 'bug'], ['name' => 'critical']],
                'comments' => 1,
                'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
                'closed_at' => null,
            ],
            'repository' => ['full_name' => 'hooktester/repo'],
        ];

        $res = $this->sendWebhook($payload, 'issues');
        $res->assertOk();

        $this->assertDatabaseHas('issues', [
            'integration_id' => $this->tracked->integration_id,
            'platform_issue_id' => '777',
            'type'     => 'bug',
            'priority' => 'critical',
            'state'    => 'open',
        ]);
    }

    public function test_review_event_creates_review_and_pr(): void
    {
        $payload = [
            'action' => 'submitted',
            'review' => [
                'id' => 888, 'state' => 'approved', 'body' => 'LGTM',
                'user' => ['login' => 'carol', 'avatar_url' => null],
                'submitted_at' => '2026-07-09T11:00:00Z',
            ],
            'pull_request' => [
                'id' => 556, 'title' => 'Fix bug', 'state' => 'open',
                'user' => ['login' => 'alice'],
                'created_at' => '2026-07-09T09:00:00Z', 'updated_at' => '2026-07-09T11:00:00Z',
            ],
            'repository' => ['full_name' => 'hooktester/repo'],
        ];

        $res = $this->sendWebhook($payload, 'pull_request_review');
        $res->assertOk();

        $this->assertDatabaseHas('reviews', [
            'platform_review_id' => '888',
            'state'              => 'approved',
        ]);
        $this->assertDatabaseHas('pull_requests', ['platform_pr_id' => '556']);
    }

    public function test_notification_dispatched_to_device(): void
    {
        // Give the user a device token and spy on FcmService
        DeviceToken::create([
            'user_id' => $this->tracked->integration->user_id,
            'token'   => 'device-abc',
            'platform'=> 'android',
        ]);

        $spy = $this->spy(FcmService::class);

        $payload = [
            'action' => 'opened',
            'pull_request' => [
                'id' => 600, 'number' => 1, 'title' => 'Notify me', 'body' => '',
                'state' => 'open', 'merged' => false,
                'user' => ['login' => 'alice'], 'head' => ['ref' => 'x'], 'base' => ['ref' => 'main'],
                'labels' => [], 'created_at' => '2026-07-09T10:00:00Z', 'updated_at' => '2026-07-09T10:00:00Z',
            ],
            'repository' => ['full_name' => 'hooktester/repo'],
        ];

        $this->sendWebhook($payload, 'pull_request')->assertOk();

        $spy->shouldHaveReceived('send')
            ->withArgs(fn($token, $title, $body, $data = []) => $token === 'device-abc' && $title === 'New Pull Request')
            ->once();
    }
}
