<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /**
     * Get all reviews by the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::whereHas('pullRequest.integration', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->with(['pullRequest']);

        // Filter by platform
        if ($request->has('platform')) {
            $query->whereHas('pullRequest.integration', function ($q) use ($request) {
                $q->where('platform', $request->platform);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('reviewed_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('reviewed_at', '<=', $request->to_date);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'reviewed_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $reviews = $query->paginate($request->get('per_page', 20));

        return response()->json($reviews);
    }

    /**
     * Get a specific review
     */
    public function show(Request $request, Review $review): JsonResponse
    {
        // Check if user owns this review
        if ($review->pullRequest->integration->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $review->load(['pullRequest']);

        return response()->json([
            'review' => $review
        ]);
    }

    /**
     * Get review statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = [
            'total_reviews' => Review::whereHas('pullRequest.integration', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),

            'approved' => Review::whereHas('pullRequest.integration', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', 'approved')->count(),

            'changes_requested' => Review::whereHas('pullRequest.integration', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', 'changes_requested')->count(),

            'commented' => Review::whereHas('pullRequest.integration', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', 'commented')->count(),

            'this_week' => Review::whereHas('pullRequest.integration', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('reviewed_at', '>=', now()->startOfWeek())->count(),

            'this_month' => Review::whereHas('pullRequest.integration', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('reviewed_at', '>=', now()->startOfMonth())->count(),
        ];

        return response()->json($stats);
    }
}
