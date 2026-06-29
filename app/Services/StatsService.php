<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsService
{
    protected const array CACHE_TAGS = ['stats'];

    public function getDateFromPeriod(string $period): CarbonInterface
    {
        return match($period) {
            'day'   => now()->subDay(),
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
            default => throw new \InvalidArgumentException('Invalid period')
        };
    }

    public function getPostStatsData(string $period): array
    {
        $from = $this->getDateFromPeriod($period);

        $params = ['period' => $period];
        $key = 'stats:posts:' . md5(serialize($params));

        $data = Cache::tags([...self::CACHE_TAGS, 'posts', 'comments'])->remember($key, 600, function () use ($from, $period) {
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

            return [
                'period'          => $period,
                'status_counts'   => $statusCounts ? $statusCounts->toArray() : null,
                'top_posts'       => $topPosts->toArray(),
            ];
        });

        return $data;
    }

    public function getCommentStatsData(string $period): array
    {
        $from = $this->getDateFromPeriod($period);

        $params = ['period' => $period];
        $key = 'stats:comments:' . md5(serialize($params));

        $data = Cache::tags([...self::CACHE_TAGS, 'comments'])->remember($key, 600, function () use ($from, $period) {
            $statusCounts = Comment::selectRaw("
                COUNT(*) as count,
                COUNT(*) FILTER (WHERE created_at >= ?) as created_in_period
            ", [$from])->first();

            $activity = DB::table('comments')
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

            return [
                'period'          => $period,
                'status_counts'   => $statusCounts ? $statusCounts->toArray() : null,
                'activity'        => $activity->toArray(),
            ];
        });

        $data['activity'] = collect($data['activity'])->groupBy('day');

        return $data;
    }

    public function getUsersStatsData(): array
    {
        $key = 'stats:users:all';

        $data = Cache::tags([...self::CACHE_TAGS, 'users', 'posts', 'comments'])->remember($key, 1200, function () {
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

            return [
                'status_counts'   => $statusCounts ? $statusCounts->toArray() : null,
                'top_posters'     => $topPosters->toArray(),
                'top_commenters'  => $topCommenters->toArray(),
            ];
        });

        return $data;
    }

    public function clearCache(): bool
    {
        Cache::tags(self::CACHE_TAGS)->flush();

        return true;
    }
}
