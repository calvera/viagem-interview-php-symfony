<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Normalizes raw GET values like the legacy get_param(): an array (forged request) counts as missing.
 */
final class QueryParam
{
    public static function text(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }

    /** Only digits, anything else is ignored (legacy hidden filter). */
    public static function digits(mixed $value): string
    {
        return \is_string($value) && preg_match('/^\d+\z/', $value) ? $value : '';
    }

    /**
     * One of $allowed, otherwise ''.
     *
     * @param list<string> $allowed
     */
    public static function choice(mixed $value, array $allowed): string
    {
        return \is_string($value) && \in_array($value, $allowed, true) ? $value : '';
    }

    public static function dir(mixed $value): string
    {
        return \is_string($value) && strtolower($value) === 'desc' ? 'desc' : 'asc';
    }

    /** Doctrine sort direction of a normalized "asc"/"desc". */
    public static function direction(string $dir): \SortDirection
    {
        return $dir === 'desc' ? \SortDirection::Descending : \SortDirection::Ascending;
    }

    /** Page number from 1 (intval like legacy, the list clamps it to the last page). */
    public static function page(mixed $value): int
    {
        return \is_string($value) || \is_int($value) ? max(1, (int) $value) : 1;
    }
}
