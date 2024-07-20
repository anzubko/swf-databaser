<?php declare(strict_types=1);

namespace SWF;

use function count;

final class DatabaserQueue
{
    public const REGULAR = 0;
    public const BEGIN = 1;
    public const SAVEPOINT = 2;

    /**
     * @var string[]
     */
    private array $queries = [];

    /**
     * @var int[]
     */
    private array $types = [];

    public function add(string $query, int $type = self::REGULAR): void
    {
        $this->queries[] = $query;
        $this->types[] = $type;
    }

    public function getLastType(): ?int
    {
        return $this->types[count($this->types) - 1] ?? null;
    }

    public function pop(): void
    {
        array_pop($this->queries);
        array_pop($this->types);
    }

    public function clear(): void
    {
        $this->queries = $this->types = [];
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
        $queries = $this->queries;

        $this->queries = $this->types = [];

        return $queries;
    }
}
