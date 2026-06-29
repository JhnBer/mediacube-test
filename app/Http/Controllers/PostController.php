<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\IndexPostRequest;
use App\Http\Requests\Post\SearchPostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function __construct(protected PostService $postService)
    {
    }

    public function index(IndexPostRequest $request): JsonResponse
    {
        $dto = $request->getDto();

        return response()->json($this->postService->getPaginatedPosts($dto));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->createPost($request->validated(), $request->user());

        return response()->json((new PostResource($post))->resolve(), Response::HTTP_CREATED);
    }

    public function show(Post $post): JsonResponse
    {
        $post = $this->postService->getPostDetails($post);

        return response()->json((new PostResource($post))->resolve());
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $post = $this->postService->updatePost($post, $request->validated());

        return response()->json((new PostResource($post))->resolve());
    }

    public function search(SearchPostRequest $request): JsonResponse
    {
        $dto = $request->getDto();
        $posts = $this->postService->searchPosts($dto);

        return response()->json($posts);
    }

    public function destroy(Post $post): Response
    {
        Gate::authorize('delete', $post);

        $this->postService->deletePost($post);

        return response()->noContent();
    }
}
