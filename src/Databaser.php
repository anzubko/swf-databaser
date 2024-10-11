<?php declare(strict_types=1);

namespace SWF;

use Closure;
use SWF\Enum\DatabaserResultModeEnum;

final class Databaser
{
    /**
     * Timer of executed queries of all connections.
     */
    public static function getTimer(): float
    {
        return DatabaserParams::$timer;
    }

    /**
     * Count of executed queries of all connections.
     */
    public static function getCounter(): int
    {
        return DatabaserParams::$counter;
    }

    /**
     * Sets result conversion mode.
     */
    public static function setCamelize(bool $camelize): void
    {
        DatabaserParams::$camelize = $camelize;
    }

    /**
     * Sets fetchAll() mode.
     */
    public static function setFetchMode(DatabaserResultModeEnum $fetchMode): void
    {
        DatabaserParams::$fetchMode = $fetchMode;
    }

    /**
     * Sets external denormalizer for array to object conversions.
     */
    public static function setDenormalizer(Closure $denormalizer): void
    {
        DatabaserParams::$denormalizer = $denormalizer;
    }

    /**
     * Sets external profiler for queries.
     */
    public static function setProfiler(Closure $profiler): void
    {
        DatabaserParams::$profiler = $profiler;
    }
}
