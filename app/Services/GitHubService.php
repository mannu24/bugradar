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
     * Make authenticated request to GitHub API.
     */
    private function makeRequest(Integration $integration, string $endpoint, array $params = []): \Illuminate\Http\Client\Response
    {
        $token = decrypt($integration->access_token);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'BugRadar-App',
        ])->get(self::API_BASE . $endpoint, $params);

        if ($response->failed()) {
            throw new \Exception("GitHub API request failed: {$response->body()}");
        }

        return $response;
    }
}
