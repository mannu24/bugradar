<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    /**
     * List all issues.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Issue::whereHas('integration', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        });

        // Filters
        if ($request->has('status')) {
            $query->where('state', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
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

        $issues = $query->with('integration')->paginate(20);

        return response()->json($issues);
    }

    /**
     * Get issue details.
     */
    public function show(Request $request, Issue $issue): JsonResponse
    {
        // Check authorization
        if ($issue->integration->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $issue->load('integration');

        return response()->json([
            'issue' => $issue,
        ]);
    }

    /**
     * Get only bug issues.
     */
    public function bugs(Request $request): JsonResponse
    {
        $query = Issue::whereHas('integration', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->where('type', 'bug');

        // Sorting
        $sortBy = $request->get('sort_by', 'priority');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $issues = $query->with('integration')->paginate(20);

        return response()->json($issues);
    }

    /**
     * Get only task issues.
     */
    public function tasks(Request $request): JsonResponse
    {
        $query = Issue::whereHas('integration', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->where('type', 'task');

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $issues = $query->with('integration')->paginate(20);

        return response()->json($issues);
    }
}
