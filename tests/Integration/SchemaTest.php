<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SchemaTest extends KernelTestCase
{
    use ResetDatabase;

    /** Migrations create exactly the mapped schema (the migrations table itself is not mapped). */
    public function testMappingIsValidAndInSyncWithMigrations(): void
    {
        $validator = new SchemaValidator(self::getContainer()->get(EntityManagerInterface::class));

        self::assertSame([], $validator->validateMapping());
        $diff = array_filter(
            $validator->getUpdateSchemaList(),
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
        );
        self::assertSame([], $diff);
    }
}
