<?php

namespace App\Services;

/**
 * Maps Bitbucket payload fields to BugRadar's enums.
 * Bitbucket uses a "kind" field for issue type and "priority" for severity.
 */
class BitbucketDataMapper
{
    /**
     * Bitbucket PR state: OPEN | MERGED | DECLINED | SUPERSEDED
     */
    public static function mapPrState(string $state): string
    {
        return match (strtoupper($state)) {
            'OPEN'                 => 'open',
            'MERGED'               => 'merged',
            'DECLINED', 'SUPERSEDED' => 'closed',
            default                => strtolower($state),
        };
    }

    /**
     * Bitbucket issue state: new | open | resolved | on hold | invalid | duplicate | wontfix | closed
     */
    public static function mapIssueState(string $state): string
    {
        return match (strtolower($state)) {
            'new', 'open'                                    => 'open',
            'resolved', 'closed', 'wontfix',
            'invalid', 'duplicate', 'on hold'                => 'closed',
            default                                           => 'open',
        };
    }

    /**
     * Bitbucket issue priority: trivial | minor | major | critical | blocker
     */
    public static function mapPriority(string $priority): string
    {
        return match (strtolower($priority)) {
            'blocker', 'critical' => 'critical',
            'major'               => 'high',
            'minor'               => 'medium',
            'trivial'             => 'low',
            default               => 'medium',
        };
    }

    /**
     * Bitbucket issue kind: bug | enhancement | proposal | task
     */
    public static function mapIssueType(string $kind): string
    {
        return match (strtolower($kind)) {
            'bug'                       => 'bug',
            'enhancement', 'proposal'   => 'feature',
            'task'                      => 'task',
            default                     => 'task',
        };
    }
}
