<?php

namespace App\Http\Controllers;

use App\Services\OAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private OAuthService $oauthService
    ) {}

    /**
     * Redirect to Google OAuth.
     */
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $socialiteUser = Socialite::driver('google')->user();
            $user = $this->oauthService->findOrCreateUser('google', $socialiteUser);

            // Log user into session for browser-based testing
            Auth::login($user);

            // Create API token with expiration (30 days)
            $token = $user->createToken(
                'mobile-app',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

            // Check if mobile app wants deep link redirect
            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?token=' . urlencode($token) . '&success=true');
            }

            // Return JSON for API/mobile apps
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 30 * 24 * 60 * 60, // 30 days in seconds
            ]);
        } catch (\Exception $e) {
            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?success=false&error=' . urlencode($e->getMessage()));
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redirect to GitHub OAuth.
     */
    public function redirectToGithub(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    /**
     * Handle GitHub OAuth callback.
     */
    public function handleGithubCallback(Request $request)
    {
        try {
            $socialiteUser = Socialite::driver('github')->user();
            $user = $this->oauthService->findOrCreateUser('github', $socialiteUser);

            // Create API token with expiration (30 days)
            $token = $user->createToken(
                'mobile-app',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

            // Check if mobile app wants deep link redirect
            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?token=' . urlencode($token) . '&success=true');
            }

            // Return JSON for API/mobile apps
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'expires_in' => 30 * 24 * 60 * 60, // 30 days in seconds
            ]);
        } catch (\Exception $e) {
            $redirectUrl = $request->get('redirect_url');
            if ($redirectUrl) {
                return redirect($redirectUrl . '?success=false&error=' . urlencode($e->getMessage()));
            }

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
