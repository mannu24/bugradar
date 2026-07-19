<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Models\TrackedRepository;
use App\Jobs\SyncGitLabData;
use App\Jobs\SyncBitbucketData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeIntegration(string $platform): Integration
    {
        $user = User::create([
            'name' => 'Reg Tester',
            'email' => "reg-{$platform}@bugradar.dev",
            'email_verified_at' => now(),
        ]);
        $integration = Integration::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'platform_user_id' => "{$platform}_1",
            'username' => 'regtester',
            'access_token' => 'fake',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);
        return $integration;
    }

    public function test_gitlab_track_registers_webhook(): void
    {
        Queue::fake();
        $integration = $this->makeIntegration('gitlab');

        Http::fake([
            // Both endpoints match projects* → use a sequence.
            // Call order in track(): (1) list projects (verify) → (2) create hook
            'gitlab.com/api/v4/projects*' => Http::sequence()
                ->push([
                    ['id' => 501, 'path_with_namespace' => 'regtester/proj', 'web_url' => 'https://gitlab.com/regtester/proj', 'visibility' => 'private'],
                ], 200)
                ->push(['id' => 999], 201),
        ]);

        $res = $this->postJson("/api/integrations/{$integration->id}/repositories/track", [
            'repo_full_name' => 'regtester/proj',
        ]);

        $res->assertOk()->assertJsonPath('realtime', true);

        $this->assertDatabaseHas('tracked_repositories', [
            'integration_id'  => $integration->id,
            'repo_full_name'  => 'regtester/proj',
            'webhook_id'      => '999',
            'webhook_active'  => true,
        ]);

        Queue::assertPushed(SyncGitLabData::class);
    }

    public function test_bitbucket_track_registers_webhook(): void
    {
        Queue::fake();
        $integration = $this->makeIntegration('bitbucket');

        $repoListResponse = [
            'values' => [[
                'uuid' => '{repo-uuid-1}',
                'full_name' => 'regtester/repo',
                'workspace' => ['slug' => 'regtester'],
                'slug' => 'repo',
                'is_private' => true,
                'has_issues' => false,
                'links' => ['html' => ['href' => 'https://bitbucket.org/regtester/repo']],
            ]]
        ];

        Http::fake([
            'api.bitbucket.org/2.0/repositories*' => Http::sequence()
                // (1) verify repo exists via fetchPlatformRepositories → getRepositories()
                ->push($repoListResponse, 200)
                // (2) create hook → /repositories/{workspace}/{slug}/hooks
                ->push(['uuid' => '{hook-uuid-abc}'], 201),
        ]);

        $res = $this->postJson("/api/integrations/{$integration->id}/repositories/track", [
            'repo_full_name' => 'regtester/repo',
        ]);

        $res->assertOk()->assertJsonPath('realtime', true);

        // Bitbucket UUID stored WITHOUT the braces
        $this->assertDatabaseHas('tracked_repositories', [
            'integration_id' => $integration->id,
            'repo_full_name' => 'regtester/repo',
            'webhook_id'     => 'hook-uuid-abc',
            'webhook_active' => true,
        ]);

        Queue::assertPushed(SyncBitbucketData::class);
    }

    public function test_gitlab_falls_back_when_hook_creation_denied(): void
    {
        Queue::fake();
        $integration = $this->makeIntegration('gitlab');

        Http::fake([
            'gitlab.com/api/v4/projects*' => Http::sequence()
                // (1) verify repo exists
                ->push([['id' => 501, 'path_with_namespace' => 'regtester/proj', 'web_url' => 'x', 'visibility' => 'private']], 200)
                // (2) hook creation denied
                ->push(['message' => '403 Forbidden'], 403),
        ]);

        $res = $this->postJson("/api/integrations/{$integration->id}/repositories/track", [
            'repo_full_name' => 'regtester/proj',
        ]);

        $res->assertOk()->assertJsonPath('realtime', false);
        $this->assertDatabaseHas('tracked_repositories', [
            'integration_id' => $integration->id,
            'webhook_active' => false,
            'webhook_id'     => null,
        ]);
    }
}
