<?php

namespace App\Http\Controllers\Concerns;

/**
 * Shared OAuth helpers used by AuthController and IntegrationController.
 *
 * Provides:
 *  - Signed, URL-safe `state` parameter (CSRF + intent/metadata carrier)
 *  - Deep-link redirect HTML response (bounces the browser back into the app)
 *
 * The state is used so a single OAuth callback can be stateless (no session
 * dependency) yet still know the intent, the user, and where to redirect.
 */
trait HandlesOAuthState
{
    /**
     * Build a signed state string: base64url(json) + '.' + HMAC-SHA256 signature.
     */
    protected function buildState(array $payload): string
    {
        $json      = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $json, config('app.key'));
        return $json . '.' . $signature;
    }

    /**
     * Verify the state string and return the decoded payload, or null if invalid.
     */
    protected function verifyState(string $state): ?array
    {
        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$json, $signature] = $parts;

        $expected = hash_hmac('sha256', $json, config('app.key'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($json), true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * URL-safe base64 encode (RFC 4648 §5): +→-, /→_, strip = padding.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe base64 decode.
     */
    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Append query params to a deep link, preserving any existing query string.
     */
    protected function buildDeepLink(string $base, array $params): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';
        return $base . $separator . http_build_query($params);
    }

    /**
     * Return an HTML page that redirects the browser back into the mobile app
     * via a custom-scheme deep link (e.g. bugradar://oauth-callback?...).
     */
    protected function deepLinkRedirectResponse(
        string $deepLink,
        string $heading,
        string $message,
        bool $isError = false
    ): \Illuminate\Http\Response {
        $bg     = $isError ? '#f44336' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        $btnBg  = $isError ? '#f44336' : '#667eea';
        $icon   = $isError ? '✗' : '✓';
        $delay  = $isError ? 3000 : 1000;
        $status = $isError ? 400 : 200;

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta http-equiv="refresh" content="{$delay};url={$deepLink}">
                <title>{$heading}</title>
                <style>
                    body { font-family: sans-serif; text-align: center; padding: 50px;
                           background: {$bg}; color: white; min-height: 100vh; margin: 0;
                           display: flex; align-items: center; justify-content: center; }
                    .card { background: white; color: #333; padding: 40px; border-radius: 20px;
                            max-width: 400px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
                    .icon { font-size: 56px; margin-bottom: 16px; }
                    h1 { margin: 0 0 12px; font-size: 24px; }
                    p { margin: 0 0 24px; color: #666; }
                    a { display: inline-block; background: {$btnBg}; color: white;
                        padding: 14px 28px; text-decoration: none; border-radius: 10px;
                        font-weight: 600; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="icon">{$icon}</div>
                    <h1>{$heading}</h1>
                    <p>{$message}</p>
                    <a href="{$deepLink}">Return to BugRadar</a>
                </div>
                <script>setTimeout(() => window.location.href = "{$deepLink}", {$delay});</script>
            </body>
            </html>
        HTML;

        return response($html, $status)->header('Content-Type', 'text/html');
    }
}
