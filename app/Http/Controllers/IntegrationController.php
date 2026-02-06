<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\GitHubService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class IntegrationController extends Controller
{
    public function __construct(
        private GitHubService $githubService
    ) {}

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

    /**
     * Redirect to GitHub OAuth for integration.
     */
    public function connectGithub(Request $request)
    {
        // Try to get user from:
        // 1. Token in query parameter (?token=xxx)
        // 2. Bearer token in Authorization header
        // 3. Session authentication
        $user = null;
        
        // Check for token in query parameter
        if ($request->has('token')) {
            $token = $request->get('token');
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if ($personalAccessToken) {
                $user = $personalAccessToken->tokenable;
            }
        }
        
        // Fallback to normal authentication
        if (!$user) {
            $user = $request->user() ?? auth()->user();
        }
        
        if (!$user) {
            // User not logged in - show error page
            return response()->view('integration-error', [
                'platform' => 'GitHub',
                'error' => 'User not authenticated. Please login first or provide a valid token.',
            ]);
        }
        
        // Store user ID in session for callback
        session(['integration_user_id' => $user->id]);
        
        // Use custom redirect URL for integration
        return Socialite::driver('github')
            ->redirectUrl(config('app.url') . '/api/integrations/github/callback')
            ->scopes(['repo', 'read:user', 'user:email', 'read:org'])
            ->redirect();
    }

    /**
     * Handle GitHub OAuth callback for integration.
     */
    public function handleGithubCallback(Request $request)
    {
        try {
            $socialiteUser = Socialite::driver('github')
                ->redirectUrl(config('app.url') . '/api/integrations/github/callback')
                ->user();
            
            // Get user from session or auth
            $user = null;
            if (session('integration_user_id')) {
                $user = \App\Models\User::find(session('integration_user_id'));
                session()->forget('integration_user_id');
            } else {
                $user = $request->user() ?? auth()->user();
            }

            if (!$user) {
                throw new \Exception('User not authenticated. Please login first.');
            }

            // Create or update integration
            $integration = Integration::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform' => 'github',
                    'platform_user_id' => $socialiteUser->getId(),
                ],
                [
                    'username' => $socialiteUser->getNickname(),
                    'access_token' => encrypt($socialiteUser->token),
                    'refresh_token' => $socialiteUser->refreshToken ? encrypt($socialiteUser->refreshToken) : null,
                    'expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                    'is_active' => true,
                ]
            );

            // Trigger initial sync
            dispatch(new \App\Jobs\SyncGitHubData($integration));

            // Return success page for browser
            return response()->view('integration-success', [
                'platform' => 'GitHub',
                'username' => $socialiteUser->getNickname(),
                'integration_id' => $integration->id,
            ]);
        } catch (\Exception $e) {
            return response()->view('integration-error', [
                'platform' => 'GitHub',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Redirect to GitLab OAuth for integration.
     */
    public function connectGitlab(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Try to get user from token parameter, bearer token, or session
        $user = null;
        
        if ($request->has('token')) {
            $token = $request->get('token');
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if ($personalAccessToken) {
                $user = $personalAccessToken->tokenable;
            }
        }
        
        if (!$user) {
            $user = $request->user() ?? auth()->user();
        }
        
        if ($user) {
            session(['integration_user_id' => $user->id]);
        }
        
        return Socialite::driver('gitlab')
            ->scopes(['read_user', 'read_api', 'read_repository'])
            ->redirect();
    }

    /**
     * Handle GitLab OAuth callback for integration.
     */
    public function handleGitlabCallback(Request $request)
    {
        try {
            $socialiteUser = Socialite::driver('gitlab')->user();
            $user = $request->user() ?? auth()->user();

            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            // Create or update integration
            $integration = Integration::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform' => 'gitlab',
                    'platform_user_id' => $socialiteUser->getId(),
                ],
                [
                    'username' => $socialiteUser->getNickname(),
                    'access_token' => encrypt($socialiteUser->token),
                    'refresh_token' => $socialiteUser->refreshToken ? encrypt($socialiteUser->refreshToken) : null,
                    'expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                    'is_active' => true,
                    'metadata' => [
                        'instance_url' => config('services.gitlab.instance_url', 'https://gitlab.com/api/v4')
                    ]
                ]
            );

            // Trigger initial sync
            dispatch(new \App\Jobs\SyncGitLabData($integration));

            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?success=true&integration_id=' . $integration->id);
            }

            return response()->json([
                'success' => true,
                'integration' => $integration,
            ]);
        } catch (\Exception $e) {
            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?success=false&error=' . urlencode($e->getMessage()));
            }

            return response()->json([
                'success' => false,
                'message' => 'Integration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redirect to Bitbucket OAuth for integration.
     */
    public function connectBitbucket(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Try to get user from token parameter, bearer token, or session
        $user = null;
        
        if ($request->has('token')) {
            $token = $request->get('token');
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if ($personalAccessToken) {
                $user = $personalAccessToken->tokenable;
            }
        }
        
        if (!$user) {
            $user = $request->user() ?? auth()->user();
        }
        
        if ($user) {
            session(['integration_user_id' => $user->id]);
        }
        
        return Socialite::driver('bitbucket')
            ->scopes(['repository', 'pullrequest', 'issue'])
            ->redirect();
    }

    /**
     * Handle Bitbucket OAuth callback for integration.
     */
    public function handleBitbucketCallback(Request $request)
    {
        try {
            $socialiteUser = Socialite::driver('bitbucket')->user();
            $user = $request->user() ?? auth()->user();

            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            // Create or update integration
            $integration = Integration::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform' => 'bitbucket',
                    'platform_user_id' => $socialiteUser->getId(),
                ],
                [
                    'username' => $socialiteUser->getNickname(),
                    'access_token' => encrypt($socialiteUser->token),
                    'refresh_token' => $socialiteUser->refreshToken ? encrypt($socialiteUser->refreshToken) : null,
                    'expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                    'is_active' => true,
                ]
            );

            // Trigger initial sync
            dispatch(new \App\Jobs\SyncBitbucketData($integration));

            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?success=true&integration_id=' . $integration->id);
            }

            return response()->json([
                'success' => true,
                'integration' => $integration,
            ]);
        } catch (\Exception $e) {
            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?success=false&error=' . urlencode($e->getMessage()));
            }

            return response()->json([
                'success' => false,
                'message' => 'Integration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

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

        // Dispatch sync job based on platform
        match ($integration->platform) {
            'github' => dispatch(new \App\Jobs\SyncGitHubData($integration)),
            'gitlab' => dispatch(new \App\Jobs\SyncGitLabData($integration)),
            'bitbucket' => dispatch(new \App\Jobs\SyncBitbucketData($integration)),
            default => throw new \Exception('Unsupported platform'),
        };

        return response()->json([
            'success' => true,
            'message' => 'Sync started',
        ]);
    }
}
