<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DevAuthController extends Controller
{
    /**
     * Development-only login helper.
     * ⚠️ ONLY WORKS IN LOCAL ENV — guarded below.
     *
     * Usage:
     *   GET /api/auth/dev-login                        → default test user
     *   GET /api/auth/dev-login?email=you@gmail.com    → token for an existing user by email
     *   GET /api/auth/dev-login?user_id=2              → token for an existing user by ID
     *
     * If ?email= is given but no matching user exists, one is created on the fly.
     * This lets you grab a token for the exact account you logged in with via Google/GitHub.
     */
    public function devLogin(Request $request): JsonResponse
    {
        if (config('app.env') !== 'local') {
            abort(403, 'This endpoint is only available in development');
        }

        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No user found for the given email/user_id.',
            ], 404);
        }

        // Fresh token each call
        $token = $user->createToken('dev-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user'    => $user,
            'token'   => $token,
            'message' => '⚠️ Development login - not for production!',
        ]);
    }

    /**
     * Resolve the user to log in as, based on query params.
     */
    private function resolveUser(Request $request): ?User
    {
        // 1. By explicit user_id
        if ($request->filled('user_id')) {
            return User::find($request->get('user_id'));
        }

        // 2. By email — find existing, or create a lightweight dev account
        if ($request->filled('email')) {
            $email = $request->get('email');

            return User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $request->get('name', explode('@', $email)[0]),
                    'avatar'            => 'https://ui-avatars.com/api/?name=' . urlencode($request->get('name', 'Dev User')),
                    'email_verified_at' => now(),
                ]
            );
        }

        // 3. Default test user
        return User::firstOrCreate(
            ['email' => 'test@bugradar.dev'],
            [
                'name'              => 'Test User',
                'avatar'            => 'https://ui-avatars.com/api/?name=Test+User',
                'email_verified_at' => now(),
            ]
        );
    }
}
