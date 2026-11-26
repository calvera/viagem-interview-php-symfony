<?php

declare(strict_types=1);

namespace App\Enum;

enum OwnerType: string
{
    case Person = 'F';
    case Company = 'P';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Fyzická osoba',
            self::Company => 'Právnická osoba',
        };
    }
}
