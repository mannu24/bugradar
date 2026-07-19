<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Concerns\HandlesOAuthState;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class IntegrationController extends Controller
{
    use HandlesOAuthState;

    /**
     * List all user integrations.
     */
    public function index(Request $request): JsonResponse
    {
        $integrations = $request->user()
            ->integrations()
            ->get();

        return response()->json([
            'integrations' => $integrations,
        ]);
    }

    // -------------------------------------------------------------------------
    // GitHub Integration
    // -------------------------------------------------------------------------

    /**
     * Redirect to GitHub OAuth for integration.
     *
     * Uses the SAME GitHub OAuth App as login — the intent is encoded in the
     * OAuth state parameter. GitHub returns to /api/auth/github/callback which
     * routes to the correct handler based on the state.
     */
    public function connectGithub(Request $request)
    {
        $user = $this->resolveUserFromRequest($request);

        if (!$user) {
            return response()->view('integration-error', [
                'platform' => 'GitHub',
                'error'    => 'User not authenticated. Please login first or provide a valid token.',
            ]);
        }

        // Delegate the OAuth redirect to AuthController which builds the signed state
        return app(AuthController::class)->redirectToGithubIntegration(
            $user->id,
            $request->get('redirect_url')
        );
    }

    // -------------------------------------------------------------------------
    // GitLab Integration
    // -------------------------------------------------------------------------

    public function connectGitlab(Request $request)
    {
        // Full `api` scope is required to create project webhooks (read_api is read-only)
        return $this->startConnect($request, 'gitlab', ['read_user', 'api', 'read_repository'], 'GitLab');
    }

    public function handleGitlabCallback(Request $request)
    {
        return $this->finishConnect($request, 'gitlab', 'GitLab', \App\Jobs\SyncGitLabData::class);
    }

    // -------------------------------------------------------------------------
    // Bitbucket Integration
    // -------------------------------------------------------------------------

    public function connectBitbucket(Request $request)
    {
        // `webhook` scope is required to create repo webhooks
        return $this->startConnect($request, 'bitbucket', ['repository', 'pullrequest', 'issue', 'webhook'], 'Bitbucket');
    }

    public function handleBitbucketCallback(Request $request)
    {
        return $this->finishConnect($request, 'bitbucket', 'Bitbucket', \App\Jobs\SyncBitbucketData::class);
    }

    // -------------------------------------------------------------------------
    // Shared connect flow (GitLab, Bitbucket, and future OAuth platforms)
    //
    // Uses the same stateless + signed-state pattern as GitHub:
    //   - user + redirect_url encoded in a signed `state` param (no session)
    //   - Socialite stateless (our HMAC state provides CSRF protection)
    //   - callback verifies state, creates integration, deep-links back to app
    // -------------------------------------------------------------------------

    /**
     * Begin an OAuth connect for the given platform.
     */
    private function startConnect(Request $request, string $driver, array $scopes, string $displayName)
    {
        $user = $this->resolveUserFromRequest($request);

        if (!$user) {
            return response()->view('integration-error', [
                'platform' => $displayName,
                'error'    => 'User not authenticated. Please login first or provide a valid token.',
            ]);
        }

        $payload = ['intent' => 'connect', 'user_id' => $user->id];
        if ($request->has('redirect_url')) {
            $payload['redirect_url'] = $request->get('redirect_url');
        }
        $state = $this->buildState($payload);

        return Socialite::driver($driver)
            ->stateless()
            ->scopes($scopes)
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * Complete an OAuth connect callback for the given platform.
     */
    private function finishConnect(Request $request, string $driver, string $displayName, string $syncJobClass)
    {
        $state       = $this->verifyState($request->get('state', ''));
        $redirectUrl = $state['redirect_url'] ?? null;

        try {
            if ($state === null) {
                throw new \Exception('Invalid OAuth state. Please try again.');
            }

            $user = User::find($state['user_id'] ?? null);
            if (!$user) {
                throw new \Exception('User not found. Please login and try again.');
            }

            $socialiteUser = Socialite::driver($driver)->stateless()->user();

            // The Integration model mutator handles token encryption — pass raw values.
            $integration = Integration::updateOrCreate(
                ['user_id' => $user->id, 'platform' => $driver],
                [
                    'platform_user_id' => $socialiteUser->getId(),
                    'username'         => $socialiteUser->getNickname(),
                    'email'            => $socialiteUser->getEmail(),
                    'avatar'           => $socialiteUser->getAvatar(),
                    'access_token'     => $socialiteUser->token,
                    'refresh_token'    => $socialiteUser->refreshToken ?: null,
                    'expires_at'       => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                    'is_active'        => true,
                ]
            );

            dispatch(new $syncJobClass($integration));

            if ($redirectUrl) {
                $deepLink = $this->buildDeepLink($redirectUrl, [
                    'success'        => 'true',
                    'type'           => 'integration',
                    'platform'       => $driver,
                    'integration_id' => $integration->id,
                ]);
                return $this->deepLinkRedirectResponse(
                    $deepLink,
                    "{$displayName} Connected!",
                    'Syncing your data… returning to BugRadar.'
                );
            }

            return response()->view('integration-success', [
                'platform'       => $displayName,
                'username'       => $socialiteUser->getNickname(),
                'integration_id' => $integration->id,
            ]);
        } catch (\Exception $e) {
            if ($redirectUrl) {
                $deepLink = $this->buildDeepLink($redirectUrl, [
                    'success'  => 'false',
                    'type'     => 'integration',
                    'platform' => $driver,
                    'error'    => $e->getMessage(),
                ]);
                return $this->deepLinkRedirectResponse($deepLink, "{$displayName} Connection Failed", $e->getMessage(), true);
            }

            return response()->view('integration-error', [
                'platform' => $displayName,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Management
    // -------------------------------------------------------------------------

    /**
     * Disconnect an integration.
     */
    public function disconnect(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $integration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Integration disconnected',
        ]);
    }

    /**
     * Manually trigger sync for an integration.
     */
    public function sync(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$integration->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Integration is not active',
            ], 400);
        }

        match ($integration->platform) {
            'github' => dispatch(new \App\Jobs\SyncGitHubData($integration)),
            'gitlab' => dispatch(new \App\Jobs\SyncGitLabData($integration)),
            'bitbucket' => dispatch(new \App\Jobs\SyncBitbucketData($integration)),
            default => throw new \Exception('Unsupported platform: ' . $integration->platform),
        };

        return response()->json([
            'success' => true,
            'message' => 'Sync started',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve authenticated user from: ?token= query param → Bearer header → session.
     */
    private function resolveUserFromRequest(Request $request): ?\App\Models\User
    {
        // 1. Token in query parameter (for mobile OAuth flow)
        if ($request->has('token')) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($request->get('token'));
            if ($pat) {
                return $pat->tokenable;
            }
        }

        // 2. Bearer token / session auth
        return $request->user() ?? auth()->user();
    }
}
