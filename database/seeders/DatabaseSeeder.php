<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $maxPostPerUser = 20;

        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/indexes',
            '--force' => true,
        ]);

        \Hash::setRounds(4);

        // User::factory(10)->create();

        //        User::factory()->create([
        //            'name' => 'Test User',
        //            'email' => 'test@example.com',
        //        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        $editors = User::factory(10)->create([
            'role' => UserRole::EDITOR,
        ]);

        $authors = User::factory(200)->create();

        $editors->each(function (User $user) {
            $publishedPosts = Post::factory(fake()->biasedNumberBetween(5, 10, fn ($x) => 1 - sqrt($x)))
                ->published()
                ->for($user, 'author')
                ->create();

            Post::factory(fake()->numberBetween(0, 5)) // drafts
                ->for($user, 'author')
                ->create();

            $comments = $publishedPosts->flatMap(function (Post $post) use ($user) {
                return Comment::factory(fake()->biasedNumberBetween(2, 10, fn ($x) => 1 - sqrt($x)))
                    ->for($post)
                    ->for($user, 'author')
                    ->make([
                        'created_at' => fake()->dateTimeBetween($post->published_at),
                        'updated_at' => fn (array $attrs) => $attrs['created_at'],
                    ])
                    ->toArray();
            });

            foreach ($comments->chunk(1000) as $chunk) {
                Comment::insert($chunk->toArray());
            }
        });

        $authors->chunk(20)->each(function ($chunk) use ($maxPostPerUser) {
            $chunk->each(function (User $user) use ($maxPostPerUser) {
                Post::factory(fake()->biasedNumberBetween(5, 10, fn ($x) => 1 - sqrt($x)))
                    ->for($user, 'author')
                    ->create();

                $publishedPosts = Post::factory(fake()->biasedNumberBetween(5, $maxPostPerUser, fn ($x) => 1 - sqrt($x)))
                    ->published()
                    ->for($user, 'author')
                    ->create();

                $comments = $publishedPosts->flatMap(function (Post $post) use ($user) {
                    return Comment::factory(fake()->biasedNumberBetween(0, 50, fn ($x) => 1 - sqrt($x)))
                        ->for($post)
                        ->for($user, 'author')
                        ->make([
                            'created_at' => fake()->dateTimeBetween($post->published_at),
                            'updated_at' => fn (array $attrs) => $attrs['created_at'],
                        ])
                        ->toArray();
                });

                foreach ($comments->chunk(1000) as $chunk) {
                    Comment::insert($chunk->toArray());
                }
            });
        });

        Artisan::call('migrate', [
            '--path' => 'database/migrations/indexes',
            '--force' => true,
        ]);
    }
}
