<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::create([
            'name' => 'Dev', 'email' => 'devtok@bugradar.dev', 'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_register_device_token(): void
    {
        $user = $this->actingUser();

        $res = $this->postJson('/api/device-tokens', [
            'token'    => 'fcm-token-abc',
            'platform' => 'android',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('device_tokens', [
            'user_id'  => $user->id,
            'token'    => 'fcm-token-abc',
            'platform' => 'android',
        ]);
    }

    public function test_register_is_idempotent(): void
    {
        $this->actingUser();

        $this->postJson('/api/device-tokens', ['token' => 'dup-token', 'platform' => 'ios'])->assertOk();
        $this->postJson('/api/device-tokens', ['token' => 'dup-token', 'platform' => 'ios'])->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_token_reassigns_to_new_user(): void
    {
        $first = $this->actingUser();
        $this->postJson('/api/device-tokens', ['token' => 'shared-device'])->assertOk();

        // Same physical device, different user logs in
        $second = User::create(['name' => 'Second', 'email' => 'second@bugradar.dev', 'email_verified_at' => now()]);
        Sanctum::actingAs($second);
        $this->postJson('/api/device-tokens', ['token' => 'shared-device'])->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'shared-device', 'user_id' => $second->id]);
    }

    public function test_delete_device_token(): void
    {
        $user = $this->actingUser();
        $this->postJson('/api/device-tokens', ['token' => 'to-remove'])->assertOk();

        $this->deleteJson('/api/device-tokens', ['token' => 'to-remove'])->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'to-remove']);
    }

    public function test_requires_auth(): void
    {
        $this->postJson('/api/device-tokens', ['token' => 'x'])->assertStatus(401);
    }

    public function test_validation_requires_token(): void
    {
        $this->actingUser();
        $this->postJson('/api/device-tokens', [])->assertStatus(422);
    }
}
