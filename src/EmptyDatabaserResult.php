<?php
declare(strict_types=1);

namespace SWF;

class EmptyDatabaserResult extends AbstractDatabaserResult
{
    protected function fetchAllRows(): array
    {
        return [];
    }

    protected function fetchNextRow(): false
    {
        return false;
    }

    protected function fetchNextRowColumn(int $i): false
    {
        return false;
    }

    protected function fetchAllRowsColumns(int $i): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): static
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function affectedRows(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function numRows(): int
    {
        return 0;
    }
}
