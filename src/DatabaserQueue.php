<?php declare(strict_types=1);

namespace SWF;

use function count;

final class DatabaserQueue
{
    public const REGULAR = 0;
    public const BEGIN = 1;
    public const SAVEPOINT = 2;

    /**
     * @var array<array{string, int}>
     */
    private array $queries = [];

    public function add(string $query, int $type = self::REGULAR): void
    {
        $this->queries[] = [$query, $type];
    }

    public function getLastType(): ?int
    {
        return count($this->queries) > 0 ? $this->queries[count($this->queries) - 1][1] : null;
    }

    public function pop(): void
    {
        array_pop($this->queries);
    }

    public function clear(): void
    {
        $this->queries = [];
    }

    public function count(): int
    {
        return count($this->queries);
    }

    /**
     * @return string[]
     */
    public function takeAwayQueries(): array
    {
        $queries = array_column($this->queries, 0);

        $this->queries = [];

        return $queries;
    }
}
