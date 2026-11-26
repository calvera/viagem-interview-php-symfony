<?php

declare(strict_types=1);

namespace App\Tests\Command;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Import from a legacy_test database with the legacy schema. Without the DAMA transaction (ANALYZE and
 * TRUNCATE commit implicitly), so tearDown empties the target.
 */
#[SkipDatabaseRollback]
final class ImportLegacyCommandTest extends KernelTestCase
{
    use ResetDatabase;

    private Connection $legacy;

    protected function setUp(): void
    {
        $params = new DsnParser(['mysql' => 'pdo_mysql'])->parse((string) $_ENV['LEGACY_DATABASE_URL']);
        $this->legacy = DriverManager::getConnection($params);
        $this->legacy->executeStatement((string) file_get_contents(__DIR__.'/../Fixtures/legacy_schema.sql'));
        $this->legacy->executeStatement(<<<'SQL'
            INSERT INTO municipalities VALUES (1, '500040', 'Lhota', 'Habřiny', 'Západní kraj');
            INSERT INTO owners VALUES
              (1, 'F', NULL, 'Jana', 'Svobodová', '1970-01-02', NULL, 'Hlavní 1', 'Lhota', '12345', 1, '', '', 0, '0000-00-00', '', '2020-01-01 10:00:00'),
              (12, 'P', NULL, '', 'Statek Lhota spol. s r.o.', NULL, '01256748', '', '', '', 1, '+420 777', 'a@b.cz', 1, '2027-01-31', 'pozn', '2021-02-03 04:05:06');
            INSERT INTO parcels VALUES (7, 1, 'Lhota', '600001', '1517/9', 8069, 'orná půda');
            INSERT INTO ownerships VALUES (3, 12, 7, 1, 2, 2209, NULL);
            INSERT INTO owner_notes VALUES (5, 12, 'Odeslán dopis.', 'operator.a', '2026-11-01 08:00:00');
            INSERT INTO report_runs VALUES (1, '2026-11-26', 'host', 2.74, 2, 'nightly_2026-11-26.txt', '2026-11-26 02:15:00');
            SQL);
    }

    protected function tearDown(): void
    {
        self::bootKernel();
        $target = $this->target();
        $target->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['report_runs', 'owner_notes', 'ownerships', 'parcels', 'owners', 'municipalities'] as $table) {
            $target->executeStatement('TRUNCATE TABLE '.$table);
        }
        $target->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function testImportCopiesRowsWithIdsAndConvertsEmptyValuesToNull(): void
    {
        $tester = $this->runImport();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $target = $this->target();
        self::assertSame(
            ['municipalities' => 1, 'owners' => 2, 'parcels' => 1, 'ownerships' => 1, 'owner_notes' => 1, 'report_runs' => 1],
            array_map(static fn (string $t): int => (int) $target->fetchOne('SELECT COUNT(*) FROM '.$t), [
                'municipalities' => 'municipalities', 'owners' => 'owners', 'parcels' => 'parcels',
                'ownerships' => 'ownerships', 'owner_notes' => 'owner_notes', 'report_runs' => 'report_runs',
            ]),
        );

        $person = $target->fetchAssociative('SELECT * FROM owners WHERE id = 1');
        self::assertNotFalse($person);
        self::assertNull($person['duedate']);
        self::assertNull($person['note']);
        self::assertNull($person['phone']);
        self::assertSame('1970-01-02', $person['birth_date']);

        $company = $target->fetchAssociative('SELECT * FROM owners WHERE id = 12');
        self::assertNotFalse($company);
        self::assertNull($company['first_name']);
        self::assertNull($company['street']);
        self::assertSame('2027-01-31', $company['duedate']);
        self::assertSame(1, (int) $company['do_not_contact']);
        self::assertStringContainsString('owners', $tester->getDisplay());
    }

    public function testPurgeAllowsRepeatedImport(): void
    {
        $this->runImport();

        self::assertSame(Command::SUCCESS, $this->runImport(['--purge' => true])->getStatusCode());
        self::assertSame(2, (int) $this->target()->fetchOne('SELECT COUNT(*) FROM owners'));
    }

    public function testOwnerWithoutMunicipalityIsReportedAndNothingIsImported(): void
    {
        $this->legacy->executeStatement('UPDATE owners SET municipality_id = 0 WHERE id = 1');

        $tester = $this->runImport();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('vlastníci bez obce (municipality_id = 0): 1', $tester->getDisplay());
        self::assertSame(0, (int) $this->target()->fetchOne('SELECT COUNT(*) FROM owners'));
    }

    /** @param array<string, mixed> $input */
    private function runImport(array $input = []): CommandTester
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:import-legacy'));
        $tester->execute($input);

        return $tester;
    }

    private function target(): Connection
    {
        return self::getContainer()->get('doctrine.dbal.default_connection');
    }
}
