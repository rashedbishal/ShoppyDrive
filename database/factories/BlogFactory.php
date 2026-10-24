<?php

namespace Database\Factories;

use App\Models\Blog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blog>
 */
class BlogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => $title,
            'slug' => str()->slug($title),
            'excerpt' => fake()->sentence(20),
            'content' => '<p>'.implode('</p><p>', fake()->paragraphs(5)).'</p>',
            'featured_image' => null,
            'author_name' => fake()->name(),
            'meta_title' => null,
            'meta_description' => null,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
