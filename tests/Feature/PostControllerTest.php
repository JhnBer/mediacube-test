<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\PostController;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $viewer;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = User::factory()->create(['role' => UserRole::VIEWER]);
        $this->author = User::factory()->create(['role' => UserRole::VIEWER]);
    }

    public function test_can_list_and_show_posts(): void
    {
        $posts = Post::factory()->count(3)->create();

        Sanctum::actingAs($this->viewer);

        $this->getJson(route('posts.index'))
            ->assertOk()
            ->assertJsonCount(3);

        $this->getJson(route('posts.show', $posts[0]->id))
            ->assertOk()
            ->assertJsonPath('title', $posts[0]->title);
    }

    public function test_can_create_post(): void
    {
        $postData = [
            'title' => 'My first post title',
            'body' => 'This is the post body text.',
        ];

        Sanctum::actingAs($this->viewer);

        $this->postJson(route('posts.store'), $postData)
            ->assertCreated()
            ->assertJsonPath('title', 'My first post title')
            ->assertJsonPath('author_id', $this->viewer->id);

        $this->assertDatabaseHas('posts', [
            'title' => 'My first post title',
            'author_id' => $this->viewer->id,
        ]);
    }

    public function test_cannot_create_post_with_duplicate_title(): void
    {
        $postData = [
            'title' => 'Unique post title',
            'body' => 'Body text.',
        ];

        Post::factory()->create([
            'title' => $postData['title'],
            'author_id' => $this->author->id,
        ]);

        Sanctum::actingAs($this->viewer);

        $this->postJson(route('posts.store'), $postData)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseHas('posts', [
            'title' => $postData['title'],
            'author_id' => $this->author->id,
        ]);
        $this->assertDatabaseMissing('posts', [
            'title' => $postData['title'],
            'author_id' => $this->viewer->id,
        ]);
    }

    public function test_viewer_can_update_and_delete_own_post(): void
    {
        $post = Post::factory()->create(['author_id' => $this->viewer->id]);

        Sanctum::actingAs($this->viewer);

        $this->patchJson(route('posts.update', $post->id), [
            'title' => 'Updated title',
        ])
            ->assertOk();

        $this->deleteJson(route('posts.destroy', $post->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_viewer_cannot_update_or_delete_other_users_post(): void
    {
        $post = Post::factory()->create(['author_id' => $this->author->id]);

        Sanctum::actingAs($this->viewer);

        $this->patchJson(route('posts.update', $post->id), [
            'title' => 'Hack title',
        ])
            ->assertForbidden();

        $this->deleteJson(route('posts.destroy', $post->id))
            ->assertForbidden();
    }

    public function test_editor_can_update_and_delete_other_users_post(): void
    {
        $editor = User::factory()->create(['role' => UserRole::EDITOR]);
        $post = Post::factory()->create(['author_id' => $this->author->id]);

        Sanctum::actingAs($editor);

        $this->patchJson(route('posts.update', $post->id), [
            'title' => 'Editor updated title',
        ])
            ->assertOk();

        $this->deleteJson(route('posts.destroy', $post->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_admin_can_update_and_delete_other_users_post(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $post = Post::factory()->create(['author_id' => $this->author->id]);

        Sanctum::actingAs($admin);

        $this->patchJson(route('posts.update', $post->id), [
            'title' => 'Admin updated title',
        ])
            ->assertOk();

        $this->deleteJson(route('posts.destroy', $post->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_store_throws_validation_exception_when_unique_constraint_violated_after_validation(): void
    {
        // эмулирует ситуацию, если из-за гони request выдал ложно отрицательный результат
        // на существование поста с уже имеющимся тайтлом

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Post::factory()->create([
            'title' => 'Race condition title',
            'author_id' => $otherUser->id,
        ]);

        $postData = [
            'title' => 'Race condition title',
            'body' => 'Body text.',
        ];

        $request = \Mockery::mock(StorePostRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn($postData);

        $request->shouldReceive('user')
            ->andReturn($user);

        try {
            app(PostController::class)->store($request);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Post with this title already exists.'],
                $e->errors()['title']
            );
        }

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseMissing('posts', [
            'title' => $postData['title'],
            'author_id' => $user->id,
        ]);
    }

    public function test_update_throws_validation_exception_when_unique_constraint_violated_after_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Post::factory()->create([
            'title' => 'Existing title',
            'author_id' => $user->id,
        ]);

        $post = Post::factory()->create([
            'title' => 'Another title',
            'author_id' => $user->id,
        ]);

        $request = \Mockery::mock(UpdatePostRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'title' => 'Existing title',
        ]);

        try {
            app(PostController::class)->update($request, $post);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Post with this title already exists.'],
                $e->errors()['title']
            );
        }

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Another title',
        ]);
    }
}
