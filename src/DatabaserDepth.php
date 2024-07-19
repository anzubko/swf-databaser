<?php declare(strict_types=1);

namespace SWF;

use SWF\Exception\DatabaserException;

final class DatabaserDepth
{
    private int $depth = 0;

    public function get(): int
    {
        return $this->depth;
    }

    public function reset(): void
    {
        $this->depth = 0;
    }

    public function inc(): void
    {
        $this->depth++;
    }

    /**
     * @throws DatabaserException
     */
    public function dec(): void
    {
        if (--$this->depth < 0) {
            throw new DatabaserException('There is no active transaction');
        }
    }
}
