<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Integration;
use App\Models\TrackedRepository;
use App\Jobs\SyncGitHubData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepositoryTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeGithubIntegration(): Integration
    {
        $user = User::create([
            'name'  => 'Repo Tester',
            'email' => 'repotest@bugradar.dev',
            'email_verified_at' => now(),
        ]);

        $integration = Integration::create([
            'user_id'          => $user->id,
            'platform'         => 'github',
            'platform_user_id' => 'gh_1',
            'username'         => 'repotester',
            'access_token'     => 'fake-token',
            'is_active'        => true,
        ]);

        Sanctum::actingAs($user);

        return $integration;
    }

    private function fakeGithubRepos(): void
    {
        Http::fake([
            'api.github.com/user/repos*' => Http::response([
                ['id' => 101, 'full_name' => 'repotester/alpha', 'html_url' => 'https://github.com/repotester/alpha', 'private' => false],
                ['id' => 102, 'full_name' => 'repotester/beta',  'html_url' => 'https://github.com/repotester/beta',  'private' => true],
            ], 200),
        ]);
    }

    public function test_lists_repositories_with_tracked_flags(): void
    {
        $integration = $this->makeGithubIntegration();
        $this->fakeGithubRepos();

        // Pre-track one repo
        TrackedRepository::create([
            'integration_id' => $integration->id,
            'platform'       => 'github',
            'repo_full_name' => 'repotester/alpha',
            'is_active'      => true,
        ]);

        $res = $this->getJson("/api/integrations/{$integration->id}/repositories");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'repositories');

        $repos = collect($res->json('repositories'));
        $this->assertTrue($repos->firstWhere('full_name', 'repotester/alpha')['tracked']);
        $this->assertFalse($repos->firstWhere('full_name', 'repotester/beta')['tracked']);
    }

    public function test_track_repository(): void
    {
        Queue::fake(); // don't actually run the sync job

        $integration = $this->makeGithubIntegration();
        $this->fakeGithubRepos();

        $res = $this->postJson("/api/integrations/{$integration->id}/repositories/track", [
            'repo_full_name' => 'repotester/beta',
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('tracked_repositories', [
            'integration_id' => $integration->id,
            'repo_full_name' => 'repotester/beta',
            'is_active'      => true,
            'repo_platform_id' => '102',
        ]);

        // A sync should have been dispatched for the tracked repo
        Queue::assertPushed(SyncGitHubData::class);
    }

    public function test_track_rejects_unknown_repo(): void
    {
        $integration = $this->makeGithubIntegration();
        $this->fakeGithubRepos();

        $res = $this->postJson("/api/integrations/{$integration->id}/repositories/track", [
            'repo_full_name' => 'repotester/does-not-exist',
        ]);

        $res->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_untrack_keeps_data_option_a(): void
    {
        $integration = $this->makeGithubIntegration();

        $tracked = TrackedRepository::create([
            'integration_id' => $integration->id,
            'platform'       => 'github',
            'repo_full_name' => 'repotester/alpha',
            'is_active'      => true,
        ]);

        $res = $this->deleteJson("/api/integrations/{$integration->id}/repositories/track", [
            'repo_full_name' => 'repotester/alpha',
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        // Row still exists (data kept), just marked inactive
        $this->assertDatabaseHas('tracked_repositories', [
            'id'        => $tracked->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_access_another_users_integration(): void
    {
        $integration = $this->makeGithubIntegration();

        // Log in as a different user
        $other = User::create([
            'name' => 'Intruder', 'email' => 'intruder@bugradar.dev', 'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($other);

        $res = $this->getJson("/api/integrations/{$integration->id}/repositories");
        $res->assertStatus(403);
    }
}
