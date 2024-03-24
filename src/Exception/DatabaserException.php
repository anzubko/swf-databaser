<?php declare(strict_types=1);

namespace SWF\Exception;

use Exception;

class DatabaserException extends Exception
{
    /**
     * Code which identifies SQL error condition.
     */
    protected string $sqlState = 'HY000';

    /**
     * Adds sqlstate to message.
     */
    public function addSqlStateToMessage(): self
    {
        $this->message = sprintf('[%s] %s', $this->sqlState, $this->message);

        return $this;
    }

    /**
     * Sets sqlstate.
     */
    public function setSqlState(string $sqlState): self
    {
        $this->sqlState = $sqlState;

        return $this;
    }

    /**
     * Gets sqlstate.
     */
    public function getSqlState(): string
    {
        return $this->sqlState;
    }
}
