<?php

namespace Database\Factories;

use App\Enums\Enum\PostStatus;
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
        $createdAt = $this->faker->dateTimeBetween('-2 years');

        return [
            'title' => $this->faker->sentence(10),
            'body' => $this->faker->paragraph(10),
            'author_id' => User::factory(),
            'published_at' => null,
            'status' => PostStatus::DRAFT,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    public function published(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => PostStatus::PUBLISHED,
                'published_at' => fake()->dateTimeBetween($attributes['created_at']),
            ];
        });
    }
}
