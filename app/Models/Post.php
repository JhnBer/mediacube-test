<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

#[Fillable(['title', 'body', 'author_id', 'published_at', 'status'])]
class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::tags(['posts'])->flush());
        static::deleted(fn () => Cache::tags(['posts'])->flush());
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'status' => PostStatus::class,
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function lastComment(): HasOne
    {
        return $this->hasOne(Comment::class)->latestOfMany();
    }

    #[Scope]
    protected function withAuthor(Builder $query): void
    {
        $query->with('author:id,name,email');
    }

    #[Scope]
    protected function status(Builder $query, PostStatus $status): void
    {
        $query->where('status', $status);
    }

    #[Scope]
    public static function search(Builder $query, string $q): void
    {
        $query->whereRaw(
            "(lower(title || ' ' || body)) LIKE ?",
            ["%" . str($q)->lower()->toString() . "%"]
        );
    }
}
