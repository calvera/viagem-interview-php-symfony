<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Owner;
use App\Enum\OwnerType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Owner> */
final class OwnerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Owner::class;
    }

    public function company(string $name, string $ico): self
    {
        return $this->with(['type' => OwnerType::Company, 'lastName' => $name, 'firstName' => null, 'ico' => $ico, 'birthDate' => null]);
    }

    protected function defaults(): array
    {
        return [
            'type' => OwnerType::Person,
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'birthDate' => new \DateTimeImmutable('1970-01-02'),
            'municipality' => MunicipalityFactory::new(),
            'createdAt' => new \DateTimeImmutable('2020-01-01 10:00:00'),
        ];
    }
}
