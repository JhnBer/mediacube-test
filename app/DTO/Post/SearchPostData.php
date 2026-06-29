<?php

namespace App\DTO\Post;

use App\Enums\PostStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class SearchPostData extends Data
{
    public function __construct(
        public readonly string $q,
        public readonly ?PostStatus $status,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
    ) {
    }

}
