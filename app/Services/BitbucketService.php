<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitbucketService
{
    protected $baseUrl = 'https://api.bitbucket.org/2.0';
    protected $accessToken;

    public function __construct($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Get authenticated user info
     */
    public function getUser()
    {
        return $this->makeRequest('GET', '/user');
    }

    /**
     * Get user's pull requests
     */
    public function getPullRequests($state = 'OPEN')
    {
        $user = $this->getUser();
        if (!$user || !isset($user['uuid'])) {
            return [];
        }

        return $this->makeRequest('GET', '/pullrequests/' . $user['uuid'], [
            'state' => $state,
            'pagelen' => 100
        ]);
    }

    /**
     * Get pull request details
     */
    public function getPullRequest($workspace, $repoSlug, $prId)
    {
        return $this->makeRequest('GET', "/repositories/{$workspace}/{$repoSlug}/pullrequests/{$prId}");
    }

    /**
     * Get pull request activity (reviews, comments)
     */
    public function getPullRequestActivity($workspace, $repoSlug, $prId)
    {
        return $this->makeRequest('GET', "/repositories/{$workspace}/{$repoSlug}/pullrequests/{$prId}/activity");
    }

    /**
     * Get user's issues
     */
    public function getIssues($workspace, $repoSlug, $state = null)
    {
        $params = ['pagelen' => 100];
        if ($state) {
            $params['q'] = "state=\"{$state}\"";
        }

        return $this->makeRequest('GET', "/repositories/{$workspace}/{$repoSlug}/issues", $params);
    }

    /**
     * Get issue details
     */
    public function getIssue($workspace, $repoSlug, $issueId)
    {
        return $this->makeRequest('GET', "/repositories/{$workspace}/{$repoSlug}/issues/{$issueId}");
    }

    /**
     * Get user's repositories
     */
    public function getRepositories()
    {
        return $this->makeRequest('GET', '/repositories', [
            'role' => 'member',
            'pagelen' => 100
        ]);
    }

    /**
     * Get repository details
     */
    public function getRepository($workspace, $repoSlug)
    {
        return $this->makeRequest('GET', "/repositories/{$workspace}/{$repoSlug}");
    }

    /**
     * Get user's workspaces
     */
    public function getWorkspaces()
    {
        return $this->makeRequest('GET', '/workspaces', [
            'pagelen' => 100
        ]);
    }

    // -------------------------------------------------------------------------
    // Webhooks
    // -------------------------------------------------------------------------

    /**
     * Create a webhook on a repository.
     * Bitbucket Cloud webhooks send X-Hub-Signature HMAC-SHA256 headers when
     * a `secret` is set on the hook — same format as GitHub.
     *
     * @return string  The created hook's UUID.
     * @throws \Exception on failure.
     */
    public function createWebhook(
        string $workspace,
        string $repoSlug,
        string $callbackUrl,
        string $secret,
        array $events = [
            'pullrequest:created',
            'pullrequest:updated',
            'pullrequest:approved',
            'pullrequest:unapproved',
            'pullrequest:fulfilled',
            'pullrequest:rejected',
            'issue:created',
            'issue:updated',
        ]
    ): string {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept'        => 'application/json',
        ])->post($this->baseUrl . "/repositories/{$workspace}/{$repoSlug}/hooks", [
            'description' => 'BugRadar',
            'url'         => $callbackUrl,
            'active'      => true,
            'events'      => $events,
            'secret'      => $secret,
        ]);

        if ($response->failed()) {
            throw new \Exception("Bitbucket webhook creation failed: {$response->body()}");
        }

        // Bitbucket wraps the UUID in braces: {abc-123-...}
        $uuid = $response->json()['uuid'] ?? '';
        return trim($uuid, '{}');
    }

    /**
     * Delete a webhook from a repository.
     */
    public function deleteWebhook(string $workspace, string $repoSlug, string $hookId): void
    {
        // Bitbucket UUIDs must be wrapped in braces for the DELETE call
        $uuid = str_starts_with($hookId, '{') ? $hookId : '{' . $hookId . '}';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept'        => 'application/json',
        ])->delete($this->baseUrl . "/repositories/{$workspace}/{$repoSlug}/hooks/{$uuid}");

        if ($response->failed()) {
            throw new \Exception("Bitbucket webhook deletion failed: {$response->body()}");
        }
    }

    /**
     * Make HTTP request to Bitbucket API
     */
    protected function makeRequest($method, $endpoint, $params = [])
    {
        try {
            $url = $this->baseUrl . $endpoint;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ])->$method($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                // Bitbucket uses paginated responses with 'values' array
                return $data['values'] ?? $data;
            }

            Log::error('Bitbucket API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Bitbucket API Exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }
}
