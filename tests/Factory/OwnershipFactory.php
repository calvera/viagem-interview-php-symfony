<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Ownership;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Ownership> */
final class OwnershipFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Ownership::class;
    }

    protected function defaults(): array
    {
        return [
            'owner' => OwnerFactory::new(),
            'parcel' => ParcelFactory::new(),
            'shareNum' => 1,
            'shareDen' => 1,
            'lvNumber' => 100,
        ];
    }
}
