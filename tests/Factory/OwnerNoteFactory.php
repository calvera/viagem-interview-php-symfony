<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\OwnerNote;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<OwnerNote> */
final class OwnerNoteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return OwnerNote::class;
    }

    protected function defaults(): array
    {
        return [
            'owner' => OwnerFactory::new(),
            'note' => self::faker()->sentence(),
            'createdBy' => 'operator.a',
            'createdAt' => new \DateTimeImmutable('2026-11-01 08:00:00'),
        ];
    }
}
