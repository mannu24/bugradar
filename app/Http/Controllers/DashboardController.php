<?php

namespace App\Http\Controllers;

use App\Models\PullRequest;
use App\Models\Issue;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Total open PRs
        $openPrs = PullRequest::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('state', 'open')->count();

        // Total assigned issues
        $assignedIssues = Issue::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('state', 'open')->count();

        // Pending reviews (PRs with review requested)
        // Note: metadata column not available yet, so we'll skip this for now
        $pendingReviews = 0;

        // Total reviews done
        $totalReviews = Review::count();

        // PRs by platform
        $prsByPlatform = PullRequest::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->join('integrations', 'pull_requests.integration_id', '=', 'integrations.id')
        ->select('integrations.platform', DB::raw('count(*) as count'))
        ->where('pull_requests.state', 'open')
        ->groupBy('integrations.platform')
        ->get();

        // Issues by status
        $issuesByStatus = Issue::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->select('state', DB::raw('count(*) as count'))
        ->groupBy('state')
        ->get();

        // Issues by priority
        $issuesByPriority = Issue::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('state', 'open')
        ->select('priority', DB::raw('count(*) as count'))
        ->groupBy('priority')
        ->get();

        return response()->json([
            'stats' => [
                'open_prs' => $openPrs,
                'assigned_issues' => $assignedIssues,
                'total_reviews' => $totalReviews,
            ],
            'charts' => [
                'prs_by_platform' => $prsByPlatform,
                'issues_by_status' => $issuesByStatus,
                'issues_by_priority' => $issuesByPriority,
            ],
        ]);
    }

    /**
     * Get recent activity.
     */
    public function recent(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Recent PRs (last 10)
        $recentPrs = PullRequest::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('integration')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get();

        // Recent issues (last 10)
        $recentIssues = Issue::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('integration')
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get();

        // Recent reviews (last 10)
        $recentReviews = Review::whereHas('integration', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with('integration')
        ->orderBy('reviewed_at', 'desc')
        ->limit(10)
        ->get();

        return response()->json([
            'recent_prs' => $recentPrs,
            'recent_issues' => $recentIssues,
            'recent_reviews' => $recentReviews,
        ]);
    }
}
