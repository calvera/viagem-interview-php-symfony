<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Municipality;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Municipality> */
final class MunicipalityFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Municipality::class;
    }

    protected function defaults(): array
    {
        return [
            'code' => (string) self::faker()->unique()->numberBetween(500000, 799999),
            'name' => self::faker()->unique()->city(),
            'district' => 'Habřiny',
            'region' => 'Západní kraj',
        ];
    }
}
