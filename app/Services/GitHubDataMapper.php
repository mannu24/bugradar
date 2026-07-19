<?php

namespace App\Services;

/**
 * Maps GitHub label arrays to BugRadar's priority/type enums.
 * Shared by webhook handling and (optionally) sync jobs.
 */
class GitHubDataMapper
{
    /**
     * @param array $labels  GitHub labels: [['name' => 'bug'], ...] or ['bug', ...]
     */
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

        return 'other';
    }

    /**
     * Normalize labels to a lowercase array of names.
     */
    private static function labelNames(array $labels): array
    {
        return array_map(
            fn($l) => strtolower(is_array($l) ? ($l['name'] ?? '') : (string) $l),
            $labels
        );
    }
}
