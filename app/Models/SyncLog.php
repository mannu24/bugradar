<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'integration_id',
        'sync_type',
        'status',
        'prs_synced',
        'issues_synced',
        'reviews_synced',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the integration that owns the sync log.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
