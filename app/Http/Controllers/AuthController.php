<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesOAuthState;
use App\Models\Integration;
use App\Models\User;
use App\Services\OAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    use HandlesOAuthState;

    public function __construct(
        private OAuthService $oauthService
    ) {}

    // =========================================================================
    // Google OAuth
    // =========================================================================

    public function redirectToGoogle(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Encode redirect_url into signed state — no session dependency (same as GitHub)
        $payload = ['intent' => 'login'];
        if ($request->has('redirect_url')) {
            $payload['redirect_url'] = $request->get('redirect_url');
        }
        $state = $this->buildState($payload);

        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with(['state' => $state])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            // Verify our signed state and extract redirect_url
            $state = $this->verifyState($request->get('state', ''));
            if ($state === null) {
                throw new \Exception('Invalid OAuth state. Please try again.');
            }

            $socialiteUser = Socialite::driver('google')->stateless()->user();
            $user          = $this->oauthService->findOrCreateUser('google', $socialiteUser);

            $token       = $user->createToken('mobile-app', ['*'], now()->addDays(30))->plainTextToken;
            $redirectUrl = $state['redirect_url'] ?? null;

            if ($redirectUrl) {
                $deepLink = $redirectUrl . '?token=' . urlencode($token) . '&success=true';
                return $this->deepLinkRedirectResponse($deepLink, 'Login Successful!', 'Redirecting back to BugRadar…');
            }

            return response()->json([
                'success'    => true,
                'user'       => $user,
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => 30 * 24 * 60 * 60,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GitHub OAuth — single app handles BOTH login and integration connect
    //
    // Flow:
    //   Login:       GET /api/auth/github?redirect_url=bugradar://...
    //   Integration: GET /api/integrations/github/connect?token=xxx
    //
    // Both redirect to GitHub with a signed `state` param that encodes the
    // intent ('login' or 'connect') plus any needed metadata.
    // GitHub returns to /api/auth/github/callback for both.
    // =========================================================================

    /**
     * Initiate GitHub login OAuth.
     * Called by the mobile app to authenticate the user into BugRadar.
     */
    public function redirectToGithub(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Encode everything into the signed state — no session dependency.
        $payload = ['intent' => 'login'];
        if ($request->has('redirect_url')) {
            $payload['redirect_url'] = $request->get('redirect_url');
        }
        $state = $this->buildState($payload);

        // stateless(): we provide our own HMAC-signed state for CSRF protection,
        // so Socialite must not run its own session-based state validation.
        return Socialite::driver('github')
            ->stateless()
            ->scopes(['read:user', 'user:email'])
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * Initiate GitHub integration connect OAuth.
     * Called from IntegrationController — user must be authenticated.
     * Requests broader scopes (repo access) compared to login.
     *
     * @param int         $userId       The authenticated BugRadar user ID
     * @param string|null $redirectUrl  Optional deep link to return to after connect
     */
    public function redirectToGithubIntegration(int $userId, ?string $redirectUrl = null): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $payload = ['intent' => 'connect', 'user_id' => $userId];
        if ($redirectUrl) {
            $payload['redirect_url'] = $redirectUrl;
        }
        $state = $this->buildState($payload);

        return Socialite::driver('github')
            ->stateless()
            ->scopes(['repo', 'read:user', 'user:email', 'read:org', 'admin:repo_hook'])
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * Single GitHub OAuth callback — handles both login and integration connect.
     */
    public function handleGithubCallback(Request $request)
    {
        // Decode state up front so it's available for error deep-linking too
        $state       = $this->verifyState($request->get('state', ''));
        $intent      = $state['intent'] ?? 'login';
        $redirectUrl = $state['redirect_url'] ?? null;

        try {
            if ($state === null) {
                throw new \Exception('Invalid OAuth state. Please try again.');
            }

            if ($intent === 'connect') {
                return $this->handleGithubIntegrationCallback($request, $state);
            }

            return $this->handleGithubLoginCallback($request, $state);
        } catch (\Exception $e) {
            // If we have a deep link, bounce the error back into the app
            if ($redirectUrl) {
                $deepLink = $this->buildDeepLink($redirectUrl, [
                    'success'  => 'false',
                    'type'     => $intent === 'connect' ? 'integration' : 'login',
                    'platform' => 'github',
                    'error'    => $e->getMessage(),
                ]);
                $heading = $intent === 'connect' ? 'Connection Failed' : 'Login Failed';
                return $this->deepLinkRedirectResponse($deepLink, $heading, $e->getMessage(), true);
            }

            // Browser fallback
            if ($intent === 'connect') {
                return response()->view('integration-error', ['platform' => 'GitHub', 'error' => $e->getMessage()]);
            }
            return response()->json(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // GitHub callback — login branch
    // -------------------------------------------------------------------------

    private function handleGithubLoginCallback(Request $request, array $state)
    {
        $socialiteUser = Socialite::driver('github')->stateless()->user();
        $user          = $this->oauthService->findOrCreateUser('github', $socialiteUser);
        $token         = $user->createToken('mobile-app', ['*'], now()->addDays(30))->plainTextToken;

        // redirect_url comes from the signed state (no session dependency)
        $redirectUrl = $state['redirect_url'] ?? null;

        if ($redirectUrl) {
            $deepLink = $redirectUrl . '?token=' . urlencode($token) . '&success=true';
            return $this->deepLinkRedirectResponse($deepLink, 'Login Successful!', 'Redirecting back to BugRadar…');
        }

        return response()->json([
            'success'    => true,
            'user'       => $user,
            'token'      => $token,
            'expires_in' => 30 * 24 * 60 * 60,
        ]);
    }

    // -------------------------------------------------------------------------
    // GitHub callback — integration connect branch
    // -------------------------------------------------------------------------

    private function handleGithubIntegrationCallback(Request $request, array $state)
    {
        $userId = $state['user_id'] ?? null;

        if (!$userId) {
            throw new \Exception('Missing user context in OAuth state.');
        }

        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found. Please login and try again.');
        }

        $socialiteUser = Socialite::driver('github')->stateless()->user();

        $integration = Integration::updateOrCreate(
            ['user_id' => $user->id, 'platform' => 'github'],
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

        dispatch(new \App\Jobs\SyncGitHubData($integration));

        // Deep-link back into the app if a redirect_url was provided
        $redirectUrl = $state['redirect_url'] ?? null;
        if ($redirectUrl) {
            $deepLink = $this->buildDeepLink($redirectUrl, [
                'success'        => 'true',
                'type'           => 'integration',
                'platform'       => 'github',
                'integration_id' => $integration->id,
            ]);
            return $this->deepLinkRedirectResponse(
                $deepLink,
                'GitHub Connected!',
                'Syncing your repositories… returning to BugRadar.'
            );
        }

        // Browser fallback
        return response()->view('integration-success', [
            'platform'       => 'GitHub',
            'username'       => $socialiteUser->getNickname(),
            'integration_id' => $integration->id,
        ]);
    }

    // =========================================================================
    // Standard API endpoints
    // =========================================================================

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }
}
