<?php

namespace App\Http\Controllers;

use App\Enums\Enum\PostStatus;
use App\Http\Requests\Post\IndexPostRequest;
use App\Http\Requests\Post\SearchPostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexPostRequest $request): JsonResponse
    {
        $sort = $request->input('sort', 'published_at');
        $direction = $request->input('direction', 'desc');
        $perPage = $request->input('per_page', 15);

        $key = 'posts:index:' . md5(serialize($request->all()));

        $posts = Cache::tags(['posts', 'comments'])
            ->remember($key, 300, fn () =>
                Post::with([
                    'author:id,name,email',
                    'lastComment' => fn ($q) => $q->select('comments.id', 'comments.body', 'comments.author_id', 'comments.post_id'),
                    'lastComment.author:id,name,email',
                ])
                ->select(['posts.id', 'posts.title', 'posts.author_id', 'posts.published_at', 'posts.status'])
                ->withCount('comments')
                ->orderBy($sort, $direction)
                ->paginate($perPage)
                ->toArray()
            );

//        набросок для статистики
//        $posts = Post::with([
//            'author:id,name,email',
//            'lastComment' => fn ($q) => $q->select('comments.id', 'comments.body', 'comments.author_id', 'comments.post_id'),
//            'lastComment.author:id,name,email',
//        ])
//            ->select(['posts.id', 'posts.author_id', 'posts.title', 'posts.published_at', 'posts.status', 'comments_count.comments_count'])
//            ->leftJoinSub(
//                DB::table('comments')
//                    ->selectRaw('post_id, COUNT(*) as comments_count')
//                    ->groupBy('post_id'),
//                'comments_count',
//                'comments_count.post_id',
//                '=',
//                'posts.id'
//            )
//            ->orderBy($sort, $direction)
//            ->paginate($perPage);

        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            $post = $request->user()->posts()->create($request->validated());

            return response()->json($post, 201);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'title' => ['Post with this title already exists.']
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): JsonResponse
    {
        $post->load([
            'author:id,name,email',
            'lastComment' => fn ($q) => $q->select('comments.id', 'comments.body', 'comments.author_id', 'comments.post_id'),
            'lastComment.author:id,name,email',
        ])->loadCount('comments');

        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        try {
            $post->update($request->validated());

            return response()->json($post);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'title' => ['Post with this title already exists.']
            ]);
        }
    }

    public function search(SearchPostRequest $request): JsonResponse
    {
        $q = $request->input('q');

        $params = $request->all();
        ksort($params);
        $key = 'posts:search:' . md5(serialize($params));

        $posts = Cache::tags(['posts'])
            ->remember($key, 300, fn () =>
                Post::query()
                    ->withAuthor()
                    ->select(['id', 'title', 'body', 'published_at', 'status', 'author_id'])
                    ->search($q)
                    ->when($request->enum('status', PostStatus::class), fn($q, $status) =>
                        $q->status($status)
                    )
                    ->when($request->input('published_at.from'), fn($q, $date) =>
                        $q->where('published_at', '>=', $date)
                    )
                    ->when($request->input('published_at.to'), fn($q, $date) =>
                        $q->where('published_at', '<=', $date)
                    )
                    ->orderBy('published_at', 'desc')
                    ->get()
                    ->toArray()
            );

        return response()->json($posts);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post): Response
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}
