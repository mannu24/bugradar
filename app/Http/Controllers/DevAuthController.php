<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DevAuthController extends Controller
{
    /**
     * Development-only: Login with test user
     * ⚠️ ONLY USE IN DEVELOPMENT! REMOVE IN PRODUCTION!
     */
    public function devLogin(Request $request): JsonResponse
    {
        if (config('app.env') !== 'local') {
            abort(403, 'This endpoint is only available in development');
        }

        // Create or get test user
        $user = User::firstOrCreate(
            ['email' => 'test@bugradar.dev'],
            [
                'name' => 'Test User',
                'email' => 'test@bugradar.dev',
                'avatar' => 'https://ui-avatars.com/api/?name=Test+User',
                'email_verified_at' => now(),
            ]
        );

        // Create API token
        $token = $user->createToken('dev-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'message' => '⚠️ Development login - not for production!',
        ]);
    }
}
