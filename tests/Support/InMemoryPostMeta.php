<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

use Brain\Monkey\Functions;

/**
 * In-memory post meta store for seeder unit tests.
 */
final class InMemoryPostMeta
{
    /** @var array<int, array<string, mixed>> */
    private array $store = [];

    public function install(): self
    {
        Functions\when('get_post_meta')->alias(
            fn(int $postId, string $key = '', bool $single = false) => $this->get($postId, $key, $single),
        );
        Functions\when('update_post_meta')->alias(
            fn(int $postId, string $key, mixed $value) => $this->set($postId, $key, $value),
        );

        return $this;
    }

    public function set(int $postId, string $key, mixed $value): bool
    {
        $this->store[$postId][$key] = $value;

        return true;
    }

    public function getValue(int $postId, string $key): mixed
    {
        return $this->store[$postId][$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function allForPost(int $postId): array
    {
        return $this->store[$postId] ?? [];
    }

    private function get(int $postId, string $key, bool $single): mixed
    {
        if ($key === '') {
            return $this->store[$postId] ?? [];
        }

        $value = $this->store[$postId][$key] ?? '';

        return $single ? $value : [$value];
    }
}
