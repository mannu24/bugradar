<?php

namespace App\Http\Controllers;

use App\Models\PullRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PullRequestController extends Controller
{
    /**
     * List all pull requests.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PullRequest::whereHas('integration', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        });

        // Filters
        if ($request->has('status')) {
            $query->where('state', $request->status);
        }

        if ($request->has('repository')) {
            $query->where('repository', $request->repository);
        }

        if ($request->has('platform')) {
            $query->whereHas('integration', function ($q) use ($request) {
                $q->where('platform', $request->platform);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $pullRequests = $query->with('integration')->paginate(20);

        return response()->json($pullRequests);
    }

    /**
     * Get pull request details.
     */
    public function show(Request $request, PullRequest $pullRequest): JsonResponse
    {
        // Check authorization
        if ($pullRequest->integration->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $pullRequest->load('integration', 'reviews');

        return response()->json([
            'pull_request' => $pullRequest,
        ]);
    }

    /**
     * Get reviewed pull requests.
     */
    public function reviewed(Request $request): JsonResponse
    {
        $query = PullRequest::whereHas('reviews', function ($q) use ($request) {
            $q->whereHas('integration', function ($q2) use ($request) {
                $q2->where('user_id', $request->user()->id);
            });
        });

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $pullRequests = $query->with(['integration', 'reviews'])->paginate(20);

        return response()->json($pullRequests);
    }
}
