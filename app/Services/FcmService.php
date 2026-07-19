<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging sender using the HTTP v1 API.
 *
 * Auth uses a Google service-account JSON. A short-lived OAuth2 access token is
 * minted by signing a JWT with the service account private key (RS256 via openssl,
 * no extra Composer dependency) and exchanging it at Google's token endpoint.
 *
 * If FCM is not configured, send() logs the notification and returns — this keeps
 * local/dev flows working without Firebase credentials.
 */
class FcmService
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Whether FCM credentials are configured and readable.
     */
    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');
        return $path && is_string($path) && file_exists($path) && config('services.fcm.project_id');
    }

    /**
     * Send a push notification to a single device token.
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void
    {
        if (!$this->isConfigured()) {
            Log::info('[FCM not configured] would send push', [
                'token' => substr($deviceToken, 0, 12) . '…',
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
            ]);
            return;
        }

        try {
            $accessToken = $this->getAccessToken();
            $projectId   = config('services.fcm.project_id');

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token'        => $deviceToken,
                        'notification' => ['title' => $title, 'body' => $body],
                        // FCM data values must be strings
                        'data'         => array_map('strval', $data),
                    ],
                ]);

            if ($response->failed()) {
                $this->handleSendFailure($deviceToken, $response->status(), $response->body());
            }
        } catch (\Exception $e) {
            Log::error('FCM send failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove tokens that FCM reports as invalid/unregistered.
     */
    private function handleSendFailure(string $deviceToken, int $status, string $body): void
    {
        Log::warning('FCM send returned error', ['status' => $status, 'body' => $body]);

        // 404 (UNREGISTERED) or 400 (invalid) → prune the dead token
        if (in_array($status, [400, 404], true) && str_contains($body, 'UNREGISTERED')) {
            DeviceToken::where('token', $deviceToken)->delete();
        }
    }

    /**
     * Get a cached OAuth2 access token, minting a new one if needed.
     */
    private function getAccessToken(): string
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(55), function () {
            $credentials = json_decode(file_get_contents(config('services.fcm.credentials')), true);

            $jwt      = $this->buildSignedJwt($credentials);
            $response = Http::asForm()->post(self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed()) {
                throw new \Exception('FCM token exchange failed: ' . $response->body());
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Build and RS256-sign a JWT for the Google token exchange.
     */
    private function buildSignedJwt(array $credentials): string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URI,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($claims)),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
