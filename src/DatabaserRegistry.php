<?php declare(strict_types=1);

namespace SWF;

use Closure;
use SWF\Enum\DatabaserResultModeEnum;

/**
 * @internal
 */
final class DatabaserRegistry
{
    public static float $timer = 0.0;

    public static int $counter = 0;

    public static bool $camelize = true;

    public static DatabaserResultModeEnum $fetchMode = DatabaserResultModeEnum::ASSOC;

    public static ?Closure $denormalizer = null;

    public static ?Closure $profiler = null;
}
