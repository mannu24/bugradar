<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitLabService
{
    protected $baseUrl;
    protected $accessToken;

    public function __construct($accessToken, $baseUrl = 'https://gitlab.com/api/v4')
    {
        $this->accessToken = $accessToken;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get authenticated user info
     */
    public function getUser()
    {
        return $this->makeRequest('GET', '/user');
    }

    /**
     * Get user's merge requests
     */
    public function getMergeRequests($state = 'opened', $scope = 'all')
    {
        return $this->makeRequest('GET', '/merge_requests', [
            'state' => $state,
            'scope' => $scope,
            'per_page' => 100
        ]);
    }

    /**
     * Get merge request details
     */
    public function getMergeRequest($projectId, $mrIid)
    {
        return $this->makeRequest('GET', "/projects/{$projectId}/merge_requests/{$mrIid}");
    }

    /**
     * Get merge request approvals
     */
    public function getMergeRequestApprovals($projectId, $mrIid)
    {
        return $this->makeRequest('GET', "/projects/{$projectId}/merge_requests/{$mrIid}/approvals");
    }

    /**
     * Get user's issues
     */
    public function getIssues($state = 'opened', $scope = 'all')
    {
        return $this->makeRequest('GET', '/issues', [
            'state' => $state,
            'scope' => $scope,
            'per_page' => 100
        ]);
    }

    /**
     * Get issue details
     */
    public function getIssue($projectId, $issueIid)
    {
        return $this->makeRequest('GET', "/projects/{$projectId}/issues/{$issueIid}");
    }

    /**
     * Get user's projects
     */
    public function getProjects($membership = true)
    {
        return $this->makeRequest('GET', '/projects', [
            'membership' => $membership,
            'per_page' => 100
        ]);
    }

    /**
     * Get project details
     */
    public function getProject($projectId)
    {
        return $this->makeRequest('GET', "/projects/{$projectId}");
    }

    // -------------------------------------------------------------------------
    // Webhooks
    // -------------------------------------------------------------------------

    /**
     * Create a webhook on a project.
     * GitLab uses a plaintext `token` header for verification (not HMAC).
     *
     * @return string  The created hook's ID.
     * @throws \Exception on failure.
     */
    public function createWebhook(
        int|string $projectId,
        string $callbackUrl,
        string $secret,
        array $events = ['merge_requests_events', 'issues_events', 'note_events']
    ): string {
        $payload = [
            'url'                 => $callbackUrl,
            'token'               => $secret,
            'enable_ssl_verification' => true,
            'push_events'         => false,
        ];
        foreach ($events as $event) {
            $payload[$event] = true;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept'        => 'application/json',
        ])->post($this->baseUrl . "/projects/{$projectId}/hooks", $payload);

        if ($response->failed()) {
            throw new \Exception("GitLab webhook creation failed: {$response->body()}");
        }

        return (string) $response->json()['id'];
    }

    /**
     * Delete a webhook from a project.
     */
    public function deleteWebhook(int|string $projectId, string $hookId): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept'        => 'application/json',
        ])->delete($this->baseUrl . "/projects/{$projectId}/hooks/{$hookId}");

        if ($response->failed()) {
            throw new \Exception("GitLab webhook deletion failed: {$response->body()}");
        }
    }

    /**
     * Make HTTP request to GitLab API
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
                return $response->json();
            }

            Log::error('GitLab API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('GitLab API Exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }
}
