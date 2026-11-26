<?php

declare(strict_types=1);

namespace App\Value;

/**
 * Area in m², printed as "8 069 m²" or from 10 000 m² as "2,89 ha" (legacy format_area()).
 */
final readonly class Area implements \Stringable
{
    public function __construct(public float $m2)
    {
    }

    /** Area rounded to whole m² before printing, as the owner detail shows share areas. */
    public function rounded(): self
    {
        return new self(round(self::normalize($this->m2)));
    }

    public function plus(self $other): self
    {
        return new self($this->m2 + $other->m2);
    }

    public function __toString(): string
    {
        if ($this->m2 >= 10000) {
            return number_format(self::normalize($this->m2 / 10000), 2, ',', ' ').' ha';
        }

        return number_format(self::normalize($this->m2), 0, ',', ' ').' m²';
    }

    /**
     * Value pre-rounded to 15 significant digits as round() did on PHP 7.4 (legacy area_value()), so a float
     * sum like 1/3 + 1/12 + 1/12 m² (0.49999999999999994, exactly 0.5) prints as 1 m² like in the legacy app.
     */
    private static function normalize(float $value): float
    {
        if ($value == 0) {
            return $value;
        }
        $places = 14 - (int) floor(log10(abs($value)));
        $factor = 10.0 ** abs($places);
        $scaled = $places >= 0 ? $value * $factor : $value / $factor;
        $scaled = $scaled >= 0 ? floor($scaled + 0.5) : ceil($scaled - 0.5);

        return $places >= 0 ? $scaled / $factor : $scaled * $factor;
    }
}
