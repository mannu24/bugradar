<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const API_BASE = 'https://api.github.com';

    /**
     * Get authenticated user info.
     */
    public function getUser(Integration $integration): array
    {
        $response = $this->makeRequest($integration, '/user');
        return $response->json();
    }

    /**
     * Get user's pull requests.
     */
    public function getPullRequests(Integration $integration): array
    {
        $username = $integration->username;
        
        if (empty($username)) {
            throw new \Exception('Integration username is empty. Cannot fetch pull requests.');
        }
        
        // Get PRs where user is author
        $query = "is:pr author:{$username}";
        
        $response = $this->makeRequest($integration, '/search/issues', [
            'q' => $query,
            'sort' => 'updated',
            'order' => 'desc',
            'per_page' => 100,
        ]);

        return $response->json()['items'] ?? [];
    }

    /**
     * Get user's issues.
     */
    public function getIssues(Integration $integration): array
    {
        $username = $integration->username;
        
        // Get issues assigned to user
        $query = "is:issue assignee:{$username}";
        
        $response = $this->makeRequest($integration, '/search/issues', [
            'q' => $query,
            'sort' => 'updated',
            'order' => 'desc',
            'per_page' => 100,
        ]);

        return $response->json()['items'] ?? [];
    }

    /**
     * Get PR details.
     */
    public function getPullRequestDetails(Integration $integration, string $owner, string $repo, int $number): array
    {
        $response = $this->makeRequest($integration, "/repos/{$owner}/{$repo}/pulls/{$number}");
        return $response->json();
    }

    /**
     * Get PR reviews.
     */
    public function getPullRequestReviews(Integration $integration, string $owner, string $repo, int $number): array
    {
        $response = $this->makeRequest($integration, "/repos/{$owner}/{$repo}/pulls/{$number}/reviews");
        return $response->json();
    }

    /**
     * Get issue details.
     */
    public function getIssueDetails(Integration $integration, string $owner, string $repo, int $number): array
    {
        $response = $this->makeRequest($integration, "/repos/{$owner}/{$repo}/issues/{$number}");
        return $response->json();
    }

    /**
     * Get user's reviewed PRs.
     */
    public function getReviewedPullRequests(Integration $integration): array
    {
        $username = $integration->username;
        
        // Get PRs reviewed by user
        $query = "is:pr reviewed-by:{$username}";
        
        $response = $this->makeRequest($integration, '/search/issues', [
            'q' => $query,
            'sort' => 'updated',
            'order' => 'desc',
            'per_page' => 100,
        ]);

        return $response->json()['items'] ?? [];
    }

    /**
     * Get user's repositories.
     */
    public function getRepositories(Integration $integration): array
    {
        $response = $this->makeRequest($integration, '/user/repos', [
            'affiliation' => 'owner,collaborator,organization_member',
            'per_page' => 100,
        ]);

        return $response->json();
    }

    /**
     * Create a webhook on a repository.
     *
     * @return string  The created hook's ID.
     * @throws \Exception on failure (e.g. no admin permission on the repo).
     */
    public function createWebhook(
        Integration $integration,
        string $owner,
        string $repo,
        string $callbackUrl,
        string $secret,
        array $events = ['pull_request', 'issues', 'pull_request_review']
    ): string {
        $response = Http::withHeaders($this->headers($integration))
            ->post(self::API_BASE . "/repos/{$owner}/{$repo}/hooks", [
                'name'   => 'web',
                'active' => true,
                'events' => $events,
                'config' => [
                    'url'          => $callbackUrl,
                    'content_type' => 'json',
                    'secret'       => $secret,
                    'insecure_ssl' => '0',
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("GitHub webhook creation failed: {$response->body()}");
        }

        return (string) $response->json()['id'];
    }

    /**
     * Delete a webhook from a repository. Failures are swallowed by the caller.
     */
    public function deleteWebhook(Integration $integration, string $owner, string $repo, string $hookId): void
    {
        $response = Http::withHeaders($this->headers($integration))
            ->delete(self::API_BASE . "/repos/{$owner}/{$repo}/hooks/{$hookId}");

        if ($response->failed()) {
            throw new \Exception("GitHub webhook deletion failed: {$response->body()}");
        }
    }

    /**
     * Make authenticated GET request to GitHub API.
     */
    private function makeRequest(Integration $integration, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $response = Http::withHeaders($this->headers($integration))
            ->get(self::API_BASE . $endpoint, $params);

        if ($response->failed()) {
            throw new \Exception("GitHub API request failed: {$response->body()}");
        }

        return $response;
    }

    /**
     * Standard auth headers. access_token is already decrypted by the model mutator.
     */
    private function headers(Integration $integration): array
    {
        return [
            'Authorization' => "Bearer {$integration->access_token}",
            'Accept'        => 'application/vnd.github.v3+json',
            'User-Agent'    => 'BugRadar-App',
        ];
    }
}
