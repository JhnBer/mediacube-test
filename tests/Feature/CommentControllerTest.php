<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = User::factory()->create(['role' => UserRole::VIEWER]);
    }

    public function test_comment_crud_operations(): void
    {
        $post = Post::factory()->create();

        Sanctum::actingAs($this->viewer);

        $response = $this->postJson(route('comments.store'), [
            'body' => 'This is a test comment',
            'post_id' => $post->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('body', 'This is a test comment')
            ->assertJsonPath('post_id', $post->id)
            ->assertJsonPath('author_id', $this->viewer->id);

        $commentId = $response->json('id');

        $this->getJson(route('comments.index', ['post_id' => $post->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson(route('comments.show', $commentId))
            ->assertOk()
            ->assertJsonPath('body', 'This is a test comment');

        $this->patchJson(route('comments.update', $commentId), [
            'body' => 'Updated comment body',
        ])
            ->assertOk()
            ->assertJsonPath('body', 'Updated comment body');

        $this->deleteJson(route('comments.destroy', $commentId))
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
    }

    public function test_comment_policies(): void
    {
        $owner = User::factory()->create(['role' => UserRole::VIEWER]);
        $otherUser = User::factory()->create(['role' => UserRole::VIEWER]);
        $editor = User::factory()->create(['role' => UserRole::EDITOR]);

        $comment = Comment::factory()->create(['author_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $this->patchJson(route('comments.update', $comment->id), ['body' => 'Hacked'])
            ->assertForbidden();

        $this->deleteJson(route('comments.destroy', $comment->id))
            ->assertForbidden();

        Sanctum::actingAs($editor);

        $this->deleteJson(route('comments.destroy', $comment->id))
            ->assertNoContent();
    }
}
