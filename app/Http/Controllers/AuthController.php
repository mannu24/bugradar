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
    public function redirectToGoogle(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Store redirect_url in session if provided
        if ($request->has('redirect_url')) {
            session(['oauth_redirect_url' => $request->get('redirect_url')]);
        }
        
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

            // Check for redirect URL (from query param or session)
            $redirectUrl = $request->get('redirect_url') ?? session('oauth_redirect_url');
            
            if ($redirectUrl) {
                // Clear session
                session()->forget('oauth_redirect_url');
                
                // Build deep link with token
                $deepLink = $redirectUrl . '?token=' . urlencode($token) . '&success=true';
                
                // Return HTML with meta refresh and JavaScript redirect
                return response()->make("
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <meta http-equiv='refresh' content='1;url={$deepLink}'>
                        <title>Login Successful</title>
                        <style>
                            body { font-family: sans-serif; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
                            .container { background: white; color: #333; padding: 40px; border-radius: 20px; max-width: 400px; margin: 0 auto; }
                            .success { font-size: 48px; margin-bottom: 20px; }
                            h1 { margin: 20px 0; }
                            a { display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='success'>✓</div>
                            <h1>Login Successful!</h1>
                            <p>Redirecting back to BugRadar app...</p>
                            <a href='{$deepLink}'>Click here if not redirected</a>
                        </div>
                        <script>
                            setTimeout(function() {
                                window.location.href = '{$deepLink}';
                            }, 1000);
                        </script>
                    </body>
                    </html>
                ", 200)->header('Content-Type', 'text/html');
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
            $redirectUrl = $request->get('redirect_url') ?? session('oauth_redirect_url');
            
            if ($redirectUrl) {
                session()->forget('oauth_redirect_url');
                $deepLink = $redirectUrl . '?success=false&error=' . urlencode($e->getMessage());
                
                return response()->make("
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <meta http-equiv='refresh' content='3;url={$deepLink}'>
                        <title>Login Failed</title>
                        <style>
                            body { font-family: sans-serif; text-align: center; padding: 50px; background: #f44336; color: white; }
                            .container { background: white; color: #333; padding: 40px; border-radius: 20px; max-width: 400px; margin: 0 auto; }
                            .error { font-size: 48px; margin-bottom: 20px; }
                            h1 { margin: 20px 0; }
                            a { display: inline-block; background: #f44336; color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='error'>✗</div>
                            <h1>Login Failed</h1>
                            <p>{$e->getMessage()}</p>
                            <a href='{$deepLink}'>Return to App</a>
                        </div>
                        <script>
                            setTimeout(function() {
                                window.location.href = '{$deepLink}';
                            }, 3000);
                        </script>
                    </body>
                    </html>
                ", 500)->header('Content-Type', 'text/html');
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
    public function redirectToGithub(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // Store redirect_url in session if provided
        if ($request->has('redirect_url')) {
            session(['oauth_redirect_url' => $request->get('redirect_url')]);
        }
        
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

            // Check for redirect URL (from query param or session)
            $redirectUrl = $request->get('redirect_url') ?? session('oauth_redirect_url');
            
            if ($redirectUrl) {
                // Clear session
                session()->forget('oauth_redirect_url');
                
                // Build deep link with token
                $deepLink = $redirectUrl . '?token=' . urlencode($token) . '&success=true';
                
                // Return HTML with meta refresh and JavaScript redirect
                return response()->make("
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <meta http-equiv='refresh' content='1;url={$deepLink}'>
                        <title>Login Successful</title>
                        <style>
                            body { font-family: sans-serif; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
                            .container { background: white; color: #333; padding: 40px; border-radius: 20px; max-width: 400px; margin: 0 auto; }
                            .success { font-size: 48px; margin-bottom: 20px; }
                            h1 { margin: 20px 0; }
                            a { display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='success'>✓</div>
                            <h1>Login Successful!</h1>
                            <p>Redirecting back to BugRadar app...</p>
                            <a href='{$deepLink}'>Click here if not redirected</a>
                        </div>
                        <script>
                            setTimeout(function() {
                                window.location.href = '{$deepLink}';
                            }, 1000);
                        </script>
                    </body>
                    </html>
                ", 200)->header('Content-Type', 'text/html');
            }

            // Return JSON for API/mobile apps
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'expires_in' => 30 * 24 * 60 * 60, // 30 days in seconds
            ]);
        } catch (\Exception $e) {
            $redirectUrl = $request->get('redirect_url') ?? session('oauth_redirect_url');
            
            if ($redirectUrl) {
                session()->forget('oauth_redirect_url');
                $deepLink = $redirectUrl . '?success=false&error=' . urlencode($e->getMessage());
                
                return response()->make("
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <meta http-equiv='refresh' content='3;url={$deepLink}'>
                        <title>Login Failed</title>
                        <style>
                            body { font-family: sans-serif; text-align: center; padding: 50px; background: #f44336; color: white; }
                            .container { background: white; color: #333; padding: 40px; border-radius: 20px; max-width: 400px; margin: 0 auto; }
                            .error { font-size: 48px; margin-bottom: 20px; }
                            h1 { margin: 20px 0; }
                            a { display: inline-block; background: #f44336; color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; margin-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='error'>✗</div>
                            <h1>Login Failed</h1>
                            <p>{$e->getMessage()}</p>
                            <a href='{$deepLink}'>Return to App</a>
                        </div>
                        <script>
                            setTimeout(function() {
                                window.location.href = '{$deepLink}';
                            }, 3000);
                        </script>
                    </body>
                    </html>
                ", 500)->header('Content-Type', 'text/html');
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
