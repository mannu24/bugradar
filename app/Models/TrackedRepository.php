<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackedRepository extends Model
{
    protected $fillable = [
        'integration_id',
        'platform',
        'repo_full_name',
        'repo_platform_id',
        'repo_url',
        'is_active',
        'webhook_id',
        'webhook_secret',
        'webhook_active',
        'last_synced_at',
    ];

    /**
     * Never expose the webhook secret in API responses.
     */
    protected $hidden = [
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'webhook_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Encrypt the webhook secret at rest.
     */
    public function setWebhookSecretAttribute($value): void
    {
        $this->attributes['webhook_secret'] = $value
            ? \Illuminate\Support\Facades\Crypt::encryptString($value)
            : null;
    }

    public function getWebhookSecretAttribute($value): ?string
    {
        return $value ? \Illuminate\Support\Facades\Crypt::decryptString($value) : null;
    }

    /**
     * The integration this tracked repo belongs to.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
