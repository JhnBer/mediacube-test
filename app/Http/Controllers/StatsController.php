<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Stats\CommentsStatsRequest;
use App\Http\Requests\Stats\PostsStatsRequest;
use App\Http\Requests\Stats\UsersStatsRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function posts(PostsStatsRequest $request): JsonResponse
    {
        $period = $request->input('period', 'month');

        $from = match($period) {
            'day'   => now()->subDay(),
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
        };

        $statusCounts = Post::selectRaw("
            COUNT(*) FILTER (WHERE status = 'published') as published,
            COUNT(*) FILTER (WHERE status = 'draft') as draft,
            COUNT(*) FILTER (WHERE created_at >= ?) as created_in_period,
            ROUND(
                (SELECT AVG(cnt) FROM (SELECT COUNT(*) as cnt FROM comments GROUP BY post_id) as t),
                2
            ) as avg_comments
        ", [$from])->first();

        $topPosts = Post::with('author:id,name')
            ->select(['id', 'title', 'author_id', 'published_at'])
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get();

        return response()->json([
            'period'          => $period,
            'status_counts'   => $statusCounts,
            'top_posts'       => $topPosts,
        ]);
    }

    public function comments(CommentsStatsRequest $request): JsonResponse
    {
        $period = $request->input('period', 'month');

        $from = match($period) {
            'day'   => now()->subDay(),
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
        };

        $statusCounts = Comment::selectRaw("
            COUNT(*) as count,
            COUNT(*) FILTER (WHERE created_at >= ?) as created_in_period
        ", [$from])->first();

        $activity = \DB::table('comments')
            ->where('created_at', '>=', $from)
            ->selectRaw("
                EXTRACT(ISODOW FROM created_at) as day,
                EXTRACT(HOUR FROM created_at) as hour,
                COUNT(*) as count
            ")
            ->groupByRaw("
                EXTRACT(ISODOW FROM created_at),
                EXTRACT(HOUR FROM created_at)
            ")
            ->orderByRaw("
                EXTRACT(ISODOW FROM created_at),
                EXTRACT(HOUR FROM created_at)
            ")
            ->get();

        return response()->json([
            'period'          => $period,
            'status_counts'   => $statusCounts,
            'activity'        => $activity->groupBy('day'),
        ]);
    }

    public function users(UsersStatsRequest $request): JsonResponse
    {
        $statusCounts = User::selectRaw("
            COUNT(*) FILTER (WHERE role = :admin) as admins,
            COUNT(*) FILTER (WHERE role = :editor) as editors,
            COUNT(*) FILTER (WHERE role = :viewer) as viewers
        ", [
            'admin' => UserRole::ADMIN->value,
            'editor' => UserRole::EDITOR->value,
            'viewer' => UserRole::VIEWER->value,
        ])->first();

        $topPosters = User::select('users.id', 'users.name')
            ->join('posts', 'users.id', '=', 'posts.author_id')
            ->selectRaw('COUNT(posts.id) as posts_count')
            ->groupBy('users.id')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get();

        $topCommenters = User::select('users.id', 'users.name')
            ->join('comments', 'users.id', '=', 'comments.author_id')
            ->selectRaw('COUNT(comments.id) as comments_count')
            ->groupBy('users.id')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get();

        return response()->json([
            'status_counts'   => $statusCounts,
            'top_posters'     => $topPosters,
            'top_commenters'  => $topCommenters,
        ]);
    }
}
