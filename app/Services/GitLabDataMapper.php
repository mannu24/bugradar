<?php

namespace App\Services;

/**
 * Maps GitLab label arrays to BugRadar's priority/type enums, and GitLab
 * merge-request state values to our normalized PR state.
 * Labels in GitLab webhook payloads come as [{"title":"bug"}, ...] or ["bug", ...].
 */
class GitLabDataMapper
{
    public static function determinePriority(array $labels): string
    {
        $names = self::labelNames($labels);

        if (array_intersect(['critical', 'urgent', 'priority: critical'], $names)) {
            return 'critical';
        }
        if (array_intersect(['high', 'priority: high'], $names)) {
            return 'high';
        }
        if (array_intersect(['low', 'priority: low'], $names)) {
            return 'low';
        }

        return 'medium';
    }

    public static function determineIssueType(array $labels): string
    {
        $names = self::labelNames($labels);

        if (in_array('bug', $names, true)) {
            return 'bug';
        }
        if (array_intersect(['feature', 'enhancement'], $names)) {
            return 'feature';
        }
        if (array_intersect(['task', 'chore'], $names)) {
            return 'task';
        }

        return 'task';
    }

    /**
     * GitLab MR states: opened | closed | merged | locked
     */
    public static function mapMrState(string $state, bool $merged = false): string
    {
        if ($merged) {
            return 'merged';
        }
        return match ($state) {
            'opened' => 'open',
            'merged' => 'merged',
            'closed', 'locked' => 'closed',
            default  => $state,
        };
    }

    /**
     * GitLab issue states: opened | closed
     */
    public static function mapIssueState(string $state): string
    {
        return match ($state) {
            'opened' => 'open',
            'closed' => 'closed',
            default  => $state,
        };
    }

    /**
     * @param array $labels  Mixed: strings, {title,color,...}, or {name,color,...}
     */
    private static function labelNames(array $labels): array
    {
        return array_map(function ($l) {
            if (is_array($l)) {
                return strtolower($l['title'] ?? $l['name'] ?? '');
            }
            return strtolower((string) $l);
        }, $labels);
    }

    /**
     * Extract lowercase label names as a flat string array (for storage).
     */
    public static function labelStrings(array $labels): array
    {
        return array_values(array_filter(self::labelNames($labels)));
    }
}
