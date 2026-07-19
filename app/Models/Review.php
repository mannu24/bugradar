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

    /**
     * Get the integration through the pull request.
     * Allows whereHas('integration') on Review queries.
     *
     * hasOneThrough(related, through, firstKey, secondKey, localKey, secondLocalKey)
     *   firstKey      = foreign key on the THROUGH model (PullRequest) pointing back to this model (Review)
     *   secondKey     = foreign key on the RELATED model (Integration) — its primary key = 'id'
     *   localKey      = primary key on this model (Review) = 'id'
     *   secondLocalKey= foreign key on the THROUGH model pointing to RELATED = 'integration_id'
     */
    public function integration()
    {
        return $this->hasOneThrough(
            \App\Models\Integration::class, // related
            PullRequest::class,             // through
            'id',                           // pull_requests.id  (firstKey: through PK that review.pull_request_id points to)
            'id',                           // integrations.id   (secondKey: related PK)
            'pull_request_id',              // reviews.pull_request_id (localKey: this model's FK to through)
            'integration_id'                // pull_requests.integration_id (secondLocalKey: through FK to related)
        );
    }
}
