<?php
declare(strict_types=1);

namespace SWF\Exception;

use Exception;

class DatabaserException extends Exception
{
    /**
     * Code which identifies SQL error condition.
     */
    protected string $state = 'HY000';

    public function stateToMessage(): static
    {
        $this->message = sprintf('[%s] %s', $this->state, $this->message);

        return $this;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
