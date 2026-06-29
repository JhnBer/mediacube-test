<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stats\CommentsStatsRequest;
use App\Http\Requests\Stats\PostsStatsRequest;
use App\Http\Requests\Stats\UsersStatsRequest;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StatsController extends Controller
{
    public function __construct(protected StatsService $statsService) {}

    public function posts(PostsStatsRequest $request): JsonResponse
    {
        $data = $this->statsService->getPostStatsData($request->input('period', 'month'));
        return response()->json($data);
    }

    public function comments(CommentsStatsRequest $request): JsonResponse
    {
        $data = $this->statsService->getCommentStatsData($request->input('period', 'month'));
        return response()->json($data);
    }

    public function users(UsersStatsRequest $request): JsonResponse
    {
        $data = $this->statsService->getUsersStatsData();
        return response()->json($data);
    }

    public function clearCache(): JsonResponse
    {
        if (!$this->statsService->clearCache()) {
            return response()->json([
                'message' => 'Failed to clear statistics cache.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Statistics cache cleared successfully.'
        ]);
    }
}
