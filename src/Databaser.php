<?php
declare(strict_types=1);

namespace SWF;

use Closure;
use SWF\Enum\DatabaserResultModeEnum;

final class Databaser
{
    /**
     * Gets timer of executed queries of all connections.
     */
    public static function getTimer(): float
    {
        return DatabaserRegistry::$timer;
    }

    /**
     * Gets count of executed queries of all connections.
     */
    public static function getCounter(): int
    {
        return DatabaserRegistry::$counter;
    }

    /**
     * Sets result conversion mode.
     */
    public static function setCamelize(bool $camelize): void
    {
        DatabaserRegistry::$camelize = $camelize;
    }

    /**
     * Sets fetchAll() mode.
     */
    public static function setFetchMode(DatabaserResultModeEnum $fetchMode): void
    {
        DatabaserRegistry::$fetchMode = $fetchMode;
    }

    /**
     * Sets external denormalizer for array to object conversions.
     */
    public static function setDenormalizer(Closure $denormalizer): void
    {
        DatabaserRegistry::$denormalizer = $denormalizer;
    }

    /**
     * Sets external profiler for queries.
     */
    public static function setProfiler(Closure $profiler): void
    {
        DatabaserRegistry::$profiler = $profiler;
    }
}
