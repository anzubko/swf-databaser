<?php declare(strict_types=1);

namespace SWF;

final class Databaser
{
    /**
     * Columns are returned as numeric arrays.
     */
    public const int NUM = 1;

    /**
     * Columns are returned as associative arrays.
     */
    public const int ASSOC = 2;

    /**
     * Columns are returned as objects.
     */
    public const int OBJECT = 3;
}
