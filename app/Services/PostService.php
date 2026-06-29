<?php

namespace App\Services;

use App\DTO\Post\IndexPostData;
use App\DTO\Post\SearchPostData;
use App\Enums\PostStatus;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PostService
{
    protected bool $useCache = true;

    public function disableCache(): self
    {
        $this->useCache = false;
        return $this;
    }

    public function getPaginatedPosts(IndexPostData $data): array
    {
        $params = $data->toArray();

        $key = 'posts:index:' . md5(serialize($params));
        $tags = ['posts', 'comments'];

        $fetcher = function () use ($data) {
            $paginator = Post::with([
                'author:id,name,email',
                'lastComment' => fn ($q) => $q->select('comments.id', 'comments.body', 'comments.author_id', 'comments.post_id'),
                'lastComment.author:id,name,email',
            ])
                ->select(['posts.id', 'posts.title', 'posts.body', 'posts.author_id', 'posts.published_at', 'posts.status'])
                ->withCount('comments')
                ->orderBy($data->sort, $data->direction)
                ->paginate($data->per_page);

            return PostResource::collection($paginator)->response()->getData(true);
        };

        return $this->remember($key, $tags, 300, $fetcher);
    }

    public function searchPosts(SearchPostData $data): array
    {
        $params = $data->toArray();
        ksort($params);
        $key = 'posts:search:' . md5(serialize($params));
        $tags = ['posts'];

        $fetcher = function () use ($data) {
            $posts = Post::query()
                ->withAuthor()
                ->select(['id', 'title', 'body', 'published_at', 'status', 'author_id'])
                ->search($data->q)
                ->when($data->status, function ($query) use ($data) {
                    $query->status($data->status);
                })
                ->when($data->from, fn($query, $date) =>
                    $query->where('published_at', '>=', $date)
                )
                ->when($data->to, fn($query, $date) =>
                    $query->where('published_at', '<=', $date)
                )
                ->orderBy('published_at', 'desc')
                ->get();

            return PostResource::collection($posts)->resolve();
        };

        return $this->remember($key, $tags, 300, $fetcher);
    }

    /**
     * Вспомогательный метод для прозрачного кэширования
     */
    protected function remember(string $key, array $tags, int $ttl, \Closure $callback)
    {
        if (!$this->useCache) {
            return $callback();
        }

        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    public function createPost(array $data, User $user): Post
    {
        try {
            $post = $user->posts()->create($data);
            return $post->load('author');
        } catch (UniqueConstraintViolationException $e) {
            $this->throwTitleException();
        }
    }

    public function updatePost(Post $post, array $data): Post
    {
        try {
            $post->update($data);
            return $post;
        } catch (UniqueConstraintViolationException $e) {
            $this->throwTitleException();
        }
    }

    public function getPostDetails(Post $post): Post
    {
        return $post->load([
            'author:id,name,email',
            'lastComment' => fn ($q) => $q->select('comments.id', 'comments.body', 'comments.author_id', 'comments.post_id'),
            'lastComment.author:id,name,email',
        ])->loadCount('comments');
    }

    public function deletePost(Post $post): bool
    {
        return $post->delete();
    }

    private function throwTitleException(): void
    {
        throw ValidationException::withMessages([
            'title' => ['Post with this title already exists.']
        ]);
    }
}
