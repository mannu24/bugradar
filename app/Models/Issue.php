<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    protected $fillable = [
        'integration_id',
        'platform',
        'platform_issue_id',
        'repository',
        'title',
        'description',
        'type',
        'state',
        'priority',
        'author_username',
        'author_avatar',
        'assignees',
        'labels',
        'comments_count',
        'due_date',
        'created_at_platform',
        'updated_at_platform',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'assignees' => 'array',
            'labels' => 'array',
            'due_date' => 'datetime',
            'created_at_platform' => 'datetime',
            'updated_at_platform' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the integration that owns the issue.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
