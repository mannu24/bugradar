<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Integration extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'platform_user_id',
        'username',
        'email',
        'avatar',
        'access_token',
        'refresh_token',
        'expires_at',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the integration.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pull requests for the integration.
     */
    public function pullRequests()
    {
        return $this->hasMany(PullRequest::class);
    }

    /**
     * Get the issues for the integration.
     */
    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Get the sync logs for the integration.
     */
    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Encrypt access token before saving.
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt access token when retrieving.
     */
    public function getAccessTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Encrypt refresh token before saving.
     */
    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt refresh token when retrieving.
     */
    public function getRefreshTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }
}
