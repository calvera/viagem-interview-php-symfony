<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Parcel;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Parcel> */
final class ParcelFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Parcel::class;
    }

    protected function defaults(): array
    {
        return [
            'municipality' => MunicipalityFactory::new(),
            'cadastralArea' => 'Lhota',
            'cadastralCode' => '600001',
            'parcelNumber' => (string) self::faker()->unique()->numberBetween(1, 99999),
            'areaM2' => 1000,
            'landType' => 'orná půda',
        ];
    }
}
