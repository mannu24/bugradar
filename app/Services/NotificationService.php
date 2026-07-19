<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * High-level notification dispatcher.
 * Sends a push to every registered device of a user via FCM.
 * If FCM isn't configured, it logs instead (so dev flows still work).
 */
class NotificationService
{
    public function __construct(
        private FcmService $fcm
    ) {}

    /**
     * Notify a user across all their registered devices.
     *
     * @param array<string,string> $data  Extra key/value payload for the app to route on.
     */
    public function notifyUser(?User $user, string $title, string $body, array $data = []): void
    {
        if (!$user) {
            return;
        }

        $tokens = $user->deviceTokens()->pluck('token')->all();

        if (empty($tokens)) {
            Log::info('Notification skipped — user has no device tokens', [
                'user_id' => $user->id,
                'title'   => $title,
            ]);
            return;
        }

        foreach ($tokens as $token) {
            $this->fcm->send($token, $title, $body, $data);
        }
    }
}
