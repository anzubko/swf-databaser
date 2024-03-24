<?php declare(strict_types=1);

namespace SWF;

final class Databaser
{
    /**
     * Columns are returned as numeric arrays.
     */
    public const NUM = 1;

    /**
     * Columns are returned as associative arrays.
     */
    public const ASSOC = 2;

    /**
     * Columns are returned as objects.
     */
    public const OBJECT = 3;
}
