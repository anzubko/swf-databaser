<?php declare(strict_types=1);

namespace SWF\Enum;

/**
 * @internal
 */
enum DatabaserQueueTypeEnum
{
    case REGULAR;
    case BEGIN;
    case SAVEPOINT;
}
