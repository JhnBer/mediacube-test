<?php

namespace App\DTO\Post;

use Spatie\LaravelData\Data;

class IndexPostData extends Data
{
    public function __construct(
        public readonly string $sort = 'published_at',
        public readonly string $direction = 'desc',
        public readonly int $per_page = 15,
    ) {
    }
}
