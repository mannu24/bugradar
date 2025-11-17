<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PullRequest extends Model
{
    protected $fillable = [
        'integration_id',
        'platform',
        'platform_pr_id',
        'repository',
        'title',
        'description',
        'state',
        'author_username',
        'author_avatar',
        'branch_from',
        'branch_to',
        'commits_count',
        'additions',
        'deletions',
        'comments_count',
        'review_status',
        'labels',
        'created_at_platform',
        'updated_at_platform',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'created_at_platform' => 'datetime',
            'updated_at_platform' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    /**
     * Get the integration that owns the pull request.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get the reviews for the pull request.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
