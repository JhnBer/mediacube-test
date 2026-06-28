<?php

namespace Database\Factories;

use App\Enums\Enumb\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(10),
            'body' => $this->faker->paragraph(10),
            'author_id' => User::factory(),
            'published_at' => null,
            'status' => PostStatus::DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => PostStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
