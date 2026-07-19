<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\TrackedRepository;
use App\Services\GitHubService;
use App\Services\GitLabService;
use App\Services\BitbucketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RepositoryController extends Controller
{
    public function __construct(
        private GitHubService $githubService
    ) {}

    /**
     * GET /integrations/{integration}/repositories
     *
     * List all repositories available on the platform for this integration,
     * each flagged with whether the user is currently tracking it.
     */
    public function index(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $repos = $this->fetchPlatformRepositories($integration);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch repositories: ' . $e->getMessage(),
            ], 502);
        }

        // Map of tracked repos for quick lookup
        $tracked = $integration->trackedRepositories()
            ->get()
            ->keyBy('repo_full_name');

        $data = collect($repos)->map(function ($repo) use ($tracked) {
            $t = $tracked->get($repo['full_name']);
            return [
                'full_name'   => $repo['full_name'],
                'platform_id' => $repo['platform_id'],
                'url'         => $repo['url'],
                'private'     => $repo['private'],
                'tracked'     => $t ? (bool) $t->is_active : false,
                'webhook'     => $t ? (bool) $t->webhook_active : false,
            ];
        })->values();

        return response()->json([
            'success'      => true,
            'platform'     => $integration->platform,
            'repositories' => $data,
        ]);
    }

    /**
     * POST /integrations/{integration}/repositories/track
     * Body: { "repo_full_name": "owner/repo" }
     *
     * Toggle a repository ON — mark tracked and trigger a sync.
     * (Webhook registration is added in a later step.)
     */
    public function track(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'repo_full_name' => ['required', 'string', 'max:255'],
        ]);

        // Look up the repo on the platform to capture id + url
        try {
            $repos = collect($this->fetchPlatformRepositories($integration));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify repository: ' . $e->getMessage(),
            ], 502);
        }

        $repo = $repos->firstWhere('full_name', $validated['repo_full_name']);

        if (!$repo) {
            return response()->json([
                'success' => false,
                'message' => 'Repository not found or not accessible with this integration.',
            ], 404);
        }

        $tracked = TrackedRepository::updateOrCreate(
            [
                'integration_id' => $integration->id,
                'repo_full_name' => $repo['full_name'],
            ],
            [
                'platform'         => $integration->platform,
                'repo_platform_id' => $repo['platform_id'],
                'repo_url'         => $repo['url'],
                'is_active'        => true,
            ]
        );

        // Register a webhook for real-time updates (best-effort).
        $this->registerWebhook($integration, $tracked);

        // Trigger a sync so the repo's data shows up
        $this->dispatchSync($integration);

        return response()->json([
            'success'    => true,
            'message'    => 'Repository is now being tracked.',
            'repository' => $tracked->fresh(),
            'realtime'   => (bool) $tracked->fresh()->webhook_active,
        ]);
    }

    /**
     * DELETE /integrations/{integration}/repositories/track
     * Body: { "repo_full_name": "owner/repo" }
     *
     * Toggle a repository OFF. Option A: keep already-synced data,
     * just mark inactive and stop future updates.
     * (Webhook removal is added in a later step.)
     */
    public function untrack(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'repo_full_name' => ['required', 'string', 'max:255'],
        ]);

        $tracked = TrackedRepository::where('integration_id', $integration->id)
            ->where('repo_full_name', $validated['repo_full_name'])
            ->first();

        if (!$tracked) {
            return response()->json([
                'success' => false,
                'message' => 'Repository is not tracked.',
            ], 404);
        }

        // Remove the webhook (best-effort) so we stop receiving events
        $this->unregisterWebhook($integration, $tracked);

        // Option A — keep synced data, just stop tracking
        $tracked->update(['is_active' => false, 'webhook_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Repository is no longer being tracked. Existing data was kept.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch repositories from the platform and normalize to a common shape:
     *   ['full_name' => ..., 'platform_id' => ..., 'url' => ..., 'private' => bool]
     */
    private function fetchPlatformRepositories(Integration $integration): array
    {
        return match ($integration->platform) {
            'github'    => $this->normalizeGithub($this->githubService->getRepositories($integration)),
            'gitlab'    => $this->normalizeGitlab($this->gitlabService($integration)->getProjects()),
            'bitbucket' => $this->normalizeBitbucket($this->bitbucketService($integration)->getRepositories()),
            default     => throw new \Exception('Unsupported platform: ' . $integration->platform),
        };
    }

    private function normalizeGithub($repos): array
    {
        return collect($repos ?? [])->map(fn($r) => [
            'full_name'   => $r['full_name'] ?? null,
            'platform_id' => (string) ($r['id'] ?? ''),
            'url'         => $r['html_url'] ?? null,
            'private'     => (bool) ($r['private'] ?? false),
        ])->filter(fn($r) => $r['full_name'])->values()->all();
    }

    private function normalizeGitlab($projects): array
    {
        return collect($projects ?? [])->map(fn($p) => [
            'full_name'   => $p['path_with_namespace'] ?? null,
            'platform_id' => (string) ($p['id'] ?? ''),
            'url'         => $p['web_url'] ?? null,
            'private'     => ($p['visibility'] ?? 'private') !== 'public',
        ])->filter(fn($r) => $r['full_name'])->values()->all();
    }

    private function normalizeBitbucket($repos): array
    {
        return collect($repos ?? [])->map(fn($r) => [
            'full_name'   => $r['full_name'] ?? null,
            'platform_id' => (string) ($r['uuid'] ?? ''),
            'url'         => $r['links']['html']['href'] ?? null,
            'private'     => (bool) ($r['is_private'] ?? true),
        ])->filter(fn($r) => $r['full_name'])->values()->all();
    }

    private function gitlabService(Integration $integration): GitLabService
    {
        return new GitLabService(
            $integration->access_token,
            config('services.gitlab.base_uri', 'https://gitlab.com/api/v4')
        );
    }

    private function bitbucketService(Integration $integration): BitbucketService
    {
        return new BitbucketService($integration->access_token);
    }

    private function dispatchSync(Integration $integration): void
    {
        match ($integration->platform) {
            'github'    => dispatch(new \App\Jobs\SyncGitHubData($integration)),
            'gitlab'    => dispatch(new \App\Jobs\SyncGitLabData($integration)),
            'bitbucket' => dispatch(new \App\Jobs\SyncBitbucketData($integration)),
            default     => null,
        };
    }

    /**
     * Register a webhook for real-time updates. Best-effort:
     * if the user lacks admin on the repo, we fall back to polling silently.
     */
    private function registerWebhook(Integration $integration, TrackedRepository $tracked): void
    {
        // Already has an active webhook — nothing to do.
        if ($tracked->webhook_active && $tracked->webhook_id) {
            return;
        }

        $secret      = Str::random(40);
        $callbackUrl = rtrim(config('app.url'), '/')
            . '/api/webhooks/' . $integration->platform . '/' . $tracked->id;

        try {
            $hookId = match ($integration->platform) {
                'github'    => $this->registerGithubWebhook($integration, $tracked, $callbackUrl, $secret),
                'gitlab'    => $this->registerGitlabWebhook($integration, $tracked, $callbackUrl, $secret),
                'bitbucket' => $this->registerBitbucketWebhook($integration, $tracked, $callbackUrl, $secret),
                default     => null,
            };

            if (!$hookId) {
                return; // unsupported platform
            }

            $tracked->update([
                'webhook_id'     => $hookId,
                'webhook_secret' => $secret,
                'webhook_active' => true,
            ]);
        } catch (\Exception $e) {
            // No admin/maintainer rights or other error — polling still covers this repo.
            $tracked->update(['webhook_active' => false]);
            Log::warning('Webhook registration failed; falling back to polling', [
                'platform' => $integration->platform,
                'repo'     => $tracked->repo_full_name,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove a previously registered webhook. Best-effort — swallows errors.
     */
    private function unregisterWebhook(Integration $integration, TrackedRepository $tracked): void
    {
        if (!$tracked->webhook_id) {
            return;
        }

        try {
            match ($integration->platform) {
                'github'    => $this->unregisterGithubWebhook($integration, $tracked),
                'gitlab'    => $this->unregisterGitlabWebhook($integration, $tracked),
                'bitbucket' => $this->unregisterBitbucketWebhook($integration, $tracked),
                default     => null,
            };
        } catch (\Exception $e) {
            Log::warning('Webhook deletion failed', [
                'platform' => $integration->platform,
                'repo'     => $tracked->repo_full_name,
                'error'    => $e->getMessage(),
            ]);
        }

        $tracked->update(['webhook_id' => null, 'webhook_secret' => null]);
    }

    // -------------------------------------------------------------------------
    // Per-platform webhook registration
    // -------------------------------------------------------------------------

    private function registerGithubWebhook(Integration $integration, TrackedRepository $tracked, string $url, string $secret): string
    {
        [$owner, $repo] = array_pad(explode('/', $tracked->repo_full_name, 2), 2, null);
        return $this->githubService->createWebhook($integration, $owner, $repo, $url, $secret);
    }

    private function unregisterGithubWebhook(Integration $integration, TrackedRepository $tracked): void
    {
        [$owner, $repo] = array_pad(explode('/', $tracked->repo_full_name, 2), 2, null);
        $this->githubService->deleteWebhook($integration, $owner, $repo, $tracked->webhook_id);
    }

    private function registerGitlabWebhook(Integration $integration, TrackedRepository $tracked, string $url, string $secret): string
    {
        if (!$tracked->repo_platform_id) {
            throw new \Exception('GitLab project id missing on tracked_repositories row.');
        }
        return $this->gitlabService($integration)->createWebhook($tracked->repo_platform_id, $url, $secret);
    }

    private function unregisterGitlabWebhook(Integration $integration, TrackedRepository $tracked): void
    {
        if (!$tracked->repo_platform_id) {
            return;
        }
        $this->gitlabService($integration)->deleteWebhook($tracked->repo_platform_id, $tracked->webhook_id);
    }

    private function registerBitbucketWebhook(Integration $integration, TrackedRepository $tracked, string $url, string $secret): string
    {
        [$workspace, $slug] = array_pad(explode('/', $tracked->repo_full_name, 2), 2, null);
        return $this->bitbucketService($integration)->createWebhook($workspace, $slug, $url, $secret);
    }

    private function unregisterBitbucketWebhook(Integration $integration, TrackedRepository $tracked): void
    {
        [$workspace, $slug] = array_pad(explode('/', $tracked->repo_full_name, 2), 2, null);
        $this->bitbucketService($integration)->deleteWebhook($workspace, $slug, $tracked->webhook_id);
    }
}
