<?php

namespace App\Services;

use App\Models\User;
use App\Models\OAuthProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Illuminate\Support\Facades\Hash;

class OAuthService
{
    /**
     * Find or create user from OAuth provider.
     */
    public function findOrCreateUser(string $provider, SocialiteUser $socialiteUser): User
    {
        $providerId = $socialiteUser->getId();
        $email = $socialiteUser->getEmail();
        $name = $socialiteUser->getName();
        $avatar = $socialiteUser->getAvatar();

        // Check if OAuth provider already exists
        $oauthProvider = OAuthProvider::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($oauthProvider) {
            // Update OAuth provider info
            $oauthProvider->update([
                'email' => $email,
                'avatar' => $avatar,
            ]);

            return $oauthProvider->user;
        }

        // Check if user exists by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'avatar' => $avatar,
                'email_verified_at' => now(),
            ]);
        } else {
            // Update user info if needed
            $user->update([
                'name' => $name ?? $user->name,
                'avatar' => $avatar ?? $user->avatar,
            ]);
        }

        // Create OAuth provider record
        OAuthProvider::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $email,
            'avatar' => $avatar,
        ]);

        return $user;
    }

    /**
     * Update OAuth provider tokens.
     */
    public function updateTokens(OAuthProvider $oauthProvider, ?string $accessToken, ?string $refreshToken = null, ?\DateTime $expiresAt = null): void
    {
        $oauthProvider->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ]);
    }
}

