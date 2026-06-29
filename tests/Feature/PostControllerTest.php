<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\PostController;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

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
            ->assertJsonCount(3, 'data');

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
            ->assertJsonPath('author.id', $this->viewer->id);

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

    public function test_race_condition_throws_validation_exception_on_store_and_update(): void
    {
        // эмулирует ситуацию, если из-за гонки валидатор выдал ложно отрицательный результат
        // на существование поста с уже имеющимся тайтлом

        Post::factory()->create([
            'title' => 'Race condition title (store)',
            'author_id' => $this->viewer->id,
        ]);

        $postForUpdate = Post::factory()->create([
            'title' => 'Race condition title (update)',
            'author_id' => $this->author->id,
        ]);

        $postDataForStore = [
            'title' => 'Race condition title (store)',
            'body' => 'Body text.',
        ];

        $storeRequest = \Mockery::mock(StorePostRequest::class);
        $storeRequest->shouldReceive('validated')
            ->once()
            ->andReturn($postDataForStore);
        $storeRequest->shouldReceive('user')
            ->andReturn($this->author);

        Sanctum::actingAs($this->author);

        try {
            app(PostController::class)->store($storeRequest);
            $this->fail('Expected ValidationException was not thrown for store.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Post with this title already exists.'],
                $e->errors()['title']
            );
        }

        $updateRequest = \Mockery::mock(UpdatePostRequest::class);
        $updateRequest->shouldReceive('validated')
            ->once()
            ->andReturn([
                'title' => $postDataForStore['title'],
            ]);

        $updateRequest->shouldReceive('user')
            ->andReturn($this->author);

        try {
            app(PostController::class)->update($updateRequest, $postForUpdate);
            $this->fail('Expected ValidationException was not thrown for update.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Post with this title already exists.'],
                $e->errors()['title']
            );
        }

        $this->assertSame(1, Post::where('title', $postDataForStore['title'])->count());

        \Mockery::close();
    }

    public function test_search_returns_posts_matching_title(): void
    {
        Post::factory()->published()->create(['title' => 'Laravel for beginners guide']);
        Post::factory()->published()->create(['title' => 'Advanced Laravel techniques']);
        Post::factory()->create(['title' => 'PHP for experts']);

        $response = $this->getJson(route('posts.search', ['q' => 'Laravel']))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Laravel for beginners guide', $titles);
        $this->assertContains('Advanced Laravel techniques', $titles);
    }

    public function test_search_returns_posts_matching_body(): void
    {
        Post::factory()->create([
            'title' => 'First post',
            'body' => 'This article is about PostgreSQL performance tuning',
        ]);
        Post::factory()->create([
            'title' => 'Second post',
            'body' => 'Just a random text without the keyword',
        ]);

        $this->getJson(route('posts.search', ['q' => 'PostgreSQL']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'First post');
    }

    public function test_search_is_case_insensitive(): void
    {
        Post::factory()->create(['title' => 'Welcome to the Blog']);

        $this->getJson(route('posts.search', ['q' => 'welcome']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson(route('posts.search', ['q' => 'BLOG']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson(route('posts.search', ['q' => 'WELCOME TO THE']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_search_filters_by_status(): void
    {
        Post::factory()->published()->create([
            'title' => 'Published article',
        ]);
        Post::factory()->create([
            'title' => 'Draft article',
        ]);

        $this->getJson(route('posts.search', ['q' => 'article', 'status' => 'published']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published article');

        $this->getJson(route('posts.search', ['q' => 'article', 'status' => 'draft']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Draft article');
    }

    public function test_search_filters_by_date_range(): void
    {
        Carbon::setTestNow('2026-06-01');

        Post::factory()->create([
            'title' => 'Old post',
            'published_at' => '2026-05-01',
        ]);

        Carbon::setTestNow('2026-06-15');

        Post::factory()->create([
            'title' => 'Middle post',
            'published_at' => '2026-06-10',
        ]);

        Post::factory()->create([
            'title' => 'Recent post',
            'published_at' => '2026-06-20',
        ]);

        Carbon::setTestNow();

        $response = $this->getJson(route('posts.search', ['q' => 'post', 'published_at' => ['from' => '2026-06-01', 'to' => '2026-06-15']]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('data.0.title', 'Middle post');
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        Post::factory()->create(['title' => 'Unique title']);

        $this->getJson(route('posts.search', ['q' => 'nonexistent']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_returns_author_with_each_post(): void
    {
        $author = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Post::factory()->for($author, 'author')->create([
            'title' => 'Post by John',
        ]);

        $this->getJson(route('posts.search', ['q' => 'John']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author.name', 'John Doe')
            ->assertJsonPath('data.0.author.email', 'john@example.com');
    }

    public function test_search_validates_min_query_length(): void
    {
        $this->getJson(route('posts.search', ['q' => 'ab']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_search_validates_status_enum(): void
    {
        $this->getJson(route('posts.search', ['q' => 'test', 'status' => 'invalid_status']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
