<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\DatabaserQueueTypeEnum;
use function count;

/**
 * @internal
 */
final class DatabaserQueue
{
    /**
     * @var string[]
     */
    private array $queries = [];

    /**
     * @var DatabaserQueueTypeEnum[]
     */
    private array $types = [];

    public function add(string $query, DatabaserQueueTypeEnum $type = DatabaserQueueTypeEnum::REGULAR): void
    {
        $this->queries[] = $query;
        $this->types[] = $type;
    }

    public function getLastType(): ?DatabaserQueueTypeEnum
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
