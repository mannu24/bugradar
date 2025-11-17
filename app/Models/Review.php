<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'pull_request_id',
        'platform',
        'platform_review_id',
        'reviewer_username',
        'reviewer_avatar',
        'state',
        'body',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Get the pull request that owns the review.
     */
    public function pullRequest()
    {
        return $this->belongsTo(PullRequest::class);
    }
}
