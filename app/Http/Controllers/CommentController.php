<?php

namespace App\Http\Controllers;

use App\Http\Requests\Comment\IndexCommentRequest;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Models\Comment;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexCommentRequest $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $comments = Comment::with('author:id,name,email')
            ->when($request->has('post_id'), function ($query) use ($request) {
                return $query->where('post_id', $request->post_id);
            })
            ->latest()
            ->paginate($perPage);

        return response()->json($comments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCommentRequest $request): JsonResponse
    {
        $comment = $request->user()->comments()->create($request->validated());

        return response()->json($comment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment): JsonResponse
    {
        return response()->json($comment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);

        $comment->update($request->validated());

        return response()->json($comment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
