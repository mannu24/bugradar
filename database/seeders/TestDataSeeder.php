<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Integration;
use App\Models\PullRequest;
use App\Models\Issue;
use App\Models\Review;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create test user
        $user = User::firstOrCreate(
            ['email' => 'test@bugradar.dev'],
            [
                'name' => 'Test User',
                'avatar' => 'https://ui-avatars.com/api/?name=Test+User',
                'email_verified_at' => now(),
            ]
        );

        // Create GitHub integration
        $integration = Integration::updateOrCreate(
            ['user_id' => $user->id, 'platform' => 'github'],
            [
                'platform_user_id' => 'gh_testuser_123',
                'username' => 'testuser',
                'email' => 'test@bugradar.dev',
                'access_token' => 'fake_github_token_for_testing',
                'is_active' => true,
                'last_synced_at' => now(),
            ]
        );

        // Create a second integration (GitLab) to test multi-platform
        $gitlabIntegration = Integration::updateOrCreate(
            ['user_id' => $user->id, 'platform' => 'gitlab'],
            [
                'platform_user_id' => 'gl_testuser_456',
                'username' => 'testuser_gl',
                'email' => 'test@bugradar.dev',
                'access_token' => 'fake_gitlab_token_for_testing',
                'is_active' => true,
                'last_synced_at' => now(),
            ]
        );

        // ── Pull Requests ─────────────────────────────────────────────────────
        $pr1 = PullRequest::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_pr_id' => 'gh_pr_001'],
            [
                'repository' => 'testuser/my-app',
                'title' => 'Fix: Critical auth bug on Safari',
                'description' => 'Fixes the login redirect issue on Safari 17+',
                'state' => 'open',
                'author_username' => 'testuser',
                'branch_from' => 'fix/auth-bug',
                'branch_to' => 'main',
                'labels' => ['bug', 'critical'],
                'comments_count' => 5,
                'created_at_platform' => now()->subDays(2),
                'updated_at_platform' => now()->subHours(3),
            ]
        );

        $pr2 = PullRequest::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_pr_id' => 'gh_pr_002'],
            [
                'repository' => 'testuser/my-app',
                'title' => 'Feat: Add dashboard widgets',
                'description' => 'New stat cards for the dashboard',
                'state' => 'open',
                'author_username' => 'collaborator',
                'branch_from' => 'feat/dashboard',
                'branch_to' => 'main',
                'labels' => ['feature'],
                'comments_count' => 2,
                'created_at_platform' => now()->subDays(1),
                'updated_at_platform' => now()->subHours(1),
            ]
        );

        $pr3 = PullRequest::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_pr_id' => 'gh_pr_003'],
            [
                'repository' => 'testuser/api-service',
                'title' => 'Refactor: Optimize DB queries',
                'description' => 'Reduce N+1 queries on the issues endpoint',
                'state' => 'merged',
                'author_username' => 'testuser',
                'branch_from' => 'refactor/db',
                'branch_to' => 'main',
                'labels' => ['refactor'],
                'merged_at' => now()->subHours(5),
                'created_at_platform' => now()->subDays(3),
                'updated_at_platform' => now()->subHours(5),
            ]
        );

        // GitLab MR
        $pr4 = PullRequest::updateOrCreate(
            ['integration_id' => $gitlabIntegration->id, 'platform' => 'gitlab', 'platform_pr_id' => 'gl_mr_001'],
            [
                'repository' => 'testuser/backend-api',
                'title' => 'MR: Implement rate limiting',
                'description' => 'Add rate limiting to all public API endpoints',
                'state' => 'open',
                'author_username' => 'testuser_gl',
                'branch_from' => 'feat/rate-limiting',
                'branch_to' => 'main',
                'labels' => ['feature', 'security'],
                'comments_count' => 0,
                'created_at_platform' => now()->subHours(12),
                'updated_at_platform' => now()->subHours(12),
            ]
        );

        // ── Issues ────────────────────────────────────────────────────────────
        Issue::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_issue_id' => 'gh_issue_001'],
            [
                'repository' => 'testuser/my-app',
                'title' => 'Login button broken on Safari',
                'description' => 'The login button does nothing on Safari 17. Console shows: Uncaught TypeError.',
                'type' => 'bug',
                'state' => 'open',
                'priority' => 'critical',
                'author_username' => 'reporter1',
                'assignees' => ['testuser'],
                'labels' => ['bug', 'critical'],
                'comments_count' => 3,
                'created_at_platform' => now()->subDays(1),
                'updated_at_platform' => now()->subHours(2),
            ]
        );

        Issue::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_issue_id' => 'gh_issue_002'],
            [
                'repository' => 'testuser/my-app',
                'title' => 'Implement dark mode',
                'description' => 'Add dark mode support using CSS custom properties',
                'type' => 'feature',
                'state' => 'open',
                'priority' => 'medium',
                'author_username' => 'reporter2',
                'assignees' => ['testuser'],
                'labels' => ['enhancement'],
                'comments_count' => 1,
                'created_at_platform' => now()->subDays(5),
                'updated_at_platform' => now()->subDays(1),
            ]
        );

        Issue::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_issue_id' => 'gh_issue_003'],
            [
                'repository' => 'testuser/api-service',
                'title' => 'Update API documentation',
                'description' => 'Document the new /reviews and /dashboard endpoints',
                'type' => 'task',
                'state' => 'open',
                'priority' => 'low',
                'author_username' => 'testuser',
                'assignees' => ['testuser'],
                'labels' => ['documentation'],
                'comments_count' => 0,
                'created_at_platform' => now()->subDays(2),
                'updated_at_platform' => now()->subDays(2),
            ]
        );

        Issue::updateOrCreate(
            ['integration_id' => $integration->id, 'platform' => 'github', 'platform_issue_id' => 'gh_issue_004'],
            [
                'repository' => 'testuser/my-app',
                'title' => 'Memory leak in WebSocket handler',
                'description' => 'Memory grows unbounded over 24h. Restart required every day.',
                'type' => 'bug',
                'state' => 'closed',
                'priority' => 'high',
                'author_username' => 'testuser',
                'assignees' => ['testuser'],
                'labels' => ['bug', 'high'],
                'comments_count' => 7,
                'created_at_platform' => now()->subDays(10),
                'updated_at_platform' => now()->subDays(3),
                'closed_at' => now()->subDays(3),
            ]
        );

        Issue::updateOrCreate(
            ['integration_id' => $gitlabIntegration->id, 'platform' => 'gitlab', 'platform_issue_id' => 'gl_issue_001'],
            [
                'repository' => 'testuser/backend-api',
                'title' => 'SQL injection vulnerability in search',
                'description' => 'The search endpoint is vulnerable to SQLi',
                'type' => 'bug',
                'state' => 'open',
                'priority' => 'critical',
                'author_username' => 'security_team',
                'assignees' => ['testuser_gl'],
                'labels' => ['bug', 'security', 'critical'],
                'comments_count' => 2,
                'created_at_platform' => now()->subHours(6),
                'updated_at_platform' => now()->subHours(1),
            ]
        );

        // ── Reviews ───────────────────────────────────────────────────────────
        Review::updateOrCreate(
            ['pull_request_id' => $pr1->id, 'platform' => 'github', 'platform_review_id' => 'gh_rev_001'],
            [
                'reviewer_username' => 'testuser',
                'state' => 'approved',
                'body' => 'Looks good to me! Verified on Safari. Ship it.',
                'submitted_at' => now()->subHours(2),
            ]
        );

        Review::updateOrCreate(
            ['pull_request_id' => $pr2->id, 'platform' => 'github', 'platform_review_id' => 'gh_rev_002'],
            [
                'reviewer_username' => 'testuser',
                'state' => 'changes_requested',
                'body' => 'Please add unit tests for the new widgets before merging.',
                'submitted_at' => now()->subHours(6),
            ]
        );

        Review::updateOrCreate(
            ['pull_request_id' => $pr3->id, 'platform' => 'github', 'platform_review_id' => 'gh_rev_003'],
            [
                'reviewer_username' => 'testuser',
                'state' => 'commented',
                'body' => 'Minor nit: add a composite index on (user_id, platform) for the issues table.',
                'submitted_at' => now()->subDays(1),
            ]
        );

        Review::updateOrCreate(
            ['pull_request_id' => $pr4->id, 'platform' => 'gitlab', 'platform_review_id' => 'gl_rev_001'],
            [
                'reviewer_username' => 'testuser_gl',
                'state' => 'approved',
                'body' => 'Rate limiting implementation looks correct. Approved.',
                'submitted_at' => now()->subHours(4),
            ]
        );

        $this->command->info('Test data seeded successfully!');
        $this->command->info("  User ID: {$user->id} (test@bugradar.dev)");
        $this->command->info("  GitHub integration ID: {$integration->id}");
        $this->command->info("  GitLab integration ID: {$gitlabIntegration->id}");
        $this->command->info('  4 PRs, 5 issues, 4 reviews created');
    }
}
