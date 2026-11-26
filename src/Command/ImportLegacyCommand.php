<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * One-off copy of the legacy database into the new schema, ids are kept.
 * Zero dates and empty optional strings become NULL.
 */
#[AsCommand(
    name: 'app:import-legacy',
    description: 'Převede data ze staré databáze do nového schématu.',
    help: 'Spouštějte s <info>--no-debug</info>: v debug režimu si profiler Doctrine pamatuje každý dotaz a import vyčerpá paměť.',
)]
final class ImportLegacyCommand
{
    private const int BATCH = 5000;
    private const int INSERT_CHUNK = 1000;

    /** Target table => legacy SELECT expressions (aliases are the target columns), in FK order. */
    private const array TABLES = [
        'municipalities' => ['id', 'code', 'name', 'district', 'region'],
        'owners' => [
            'id', 'type', "NULLIF(title, '') AS title", "NULLIF(first_name, '') AS first_name", 'last_name',
            "NULLIF(birth_date, '0000-00-00') AS birth_date", "NULLIF(ico, '') AS ico", "NULLIF(street, '') AS street",
            "NULLIF(city, '') AS city", "NULLIF(zip, '') AS zip", 'municipality_id', "NULLIF(phone, '') AS phone",
            "NULLIF(email, '') AS email", 'do_not_contact', "NULLIF(duedate, '0000-00-00') AS duedate",
            "NULLIF(note, '') AS note", "NULLIF(created_at, '0000-00-00 00:00:00') AS created_at",
        ],
        'parcels' => [
            'id', 'municipality_id', 'cadastral_area', 'cadastral_code', 'parcel_number', 'area_m2',
            "NULLIF(land_type, '') AS land_type",
        ],
        'ownerships' => [
            'id', 'owner_id', 'parcel_id', 'share_num', 'share_den', 'lv_number',
            "NULLIF(created_at, '0000-00-00 00:00:00') AS created_at",
        ],
        'owner_notes' => [
            'id', 'owner_id', 'note', "NULLIF(created_by, '') AS created_by",
            "NULLIF(created_at, '0000-00-00 00:00:00') AS created_at",
        ],
        'report_runs' => ['id', 'run_date', 'hostname', 'duration', 'owners_total', 'report_file', 'created_at'],
    ];

    /** Rows the new schema cannot take: owners and parcels need a municipality, owners a known type. */
    private const array INVALID_ROWS = [
        'vlastníci bez obce (municipality_id = 0)' => 'SELECT COUNT(*) FROM owners WHERE municipality_id = 0',
        'parcely bez obce (municipality_id = 0)' => 'SELECT COUNT(*) FROM parcels WHERE municipality_id = 0',
        'vlastníci s neznámým typem' => "SELECT COUNT(*) FROM owners WHERE type NOT IN ('F', 'P')",
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'doctrine.dbal.legacy_connection')]
        private readonly Connection $legacy,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Před importem smaže data v cílové databázi')]
        bool $purge = false,
    ): int {
        $errors = $this->invalidRows();
        if ($errors !== []) {
            $io->error(['Stará data obsahují záznamy, které nové schéma nepřijme:', ...$errors]);

            return Command::FAILURE;
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        if ($purge) {
            $this->purge();
        }
        foreach (self::TABLES as $table => $select) {
            $count = $this->importTable($table, $select);
            $io->writeln(sprintf('%s: %s řádků', $table, number_format($count, 0, ',', ' ')));
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $this->analyze();

        $io->table(['Tabulka', 'Stará DB', 'Nová DB'], $this->counts());

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function invalidRows(): array
    {
        $errors = [];
        foreach (self::INVALID_ROWS as $label => $sql) {
            $count = (int) $this->legacy->fetchOne($sql);
            if ($count > 0) {
                $errors[] = sprintf('%s: %d', $label, $count);
            }
        }

        return $errors;
    }

    private function purge(): void
    {
        foreach (array_reverse(array_keys(self::TABLES)) as $table) {
            $this->connection->executeStatement('TRUNCATE TABLE '.$table);
        }
    }

    /**
     * Copies the table in batches by id, one transaction per batch.
     *
     * @param list<string> $select
     */
    private function importTable(string $table, array $select): int
    {
        $sql = sprintf('SELECT %s FROM %s WHERE id > ? ORDER BY id LIMIT %d', implode(', ', $select), $table, self::BATCH);
        $lastId = 0;
        $count = 0;
        while ($rows = $this->legacy->fetchAllAssociative($sql, [$lastId], [ParameterType::INTEGER])) {
            $this->connection->transactional(function () use ($table, $rows): void {
                foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                    $this->insert($table, $chunk);
                }
            });
            $lastId = (int) end($rows)['id'];
            $count += \count($rows);
        }

        return $count;
    }

    /** @param list<array<string, mixed>> $rows */
    private function insert(string $table, array $rows): void
    {
        $columns = array_keys($rows[0]);
        $placeholders = '('.implode(', ', array_fill(0, \count($columns), '?')).')';
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, \count($rows), $placeholders)),
        );
        $this->connection->executeStatement($sql, array_merge(...array_map(array_values(...), $rows)));
    }

    /**
     * Fresh optimizer statistics (ANALYZE returns a result set, so it is fetched); histograms of the filtered owner columns without an index, as legacy
     * sql/migrations/002 (otherwise rare filter values are estimated as matching every row).
     */
    private function analyze(): void
    {
        $this->connection->fetchAllAssociative('ANALYZE TABLE '.implode(', ', array_keys(self::TABLES)));
        $this->connection->fetchAllAssociative(
            'ANALYZE TABLE owners PERSISTENT FOR COLUMNS (type, ico, municipality_id, do_not_contact) INDEXES ()',
        );
    }

    /** @return list<array{string, string, string}> */
    private function counts(): array
    {
        $counts = [];
        foreach (array_keys(self::TABLES) as $table) {
            $counts[] = [
                $table,
                number_format((int) $this->legacy->fetchOne('SELECT COUNT(*) FROM '.$table), 0, ',', ' '),
                number_format((int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table), 0, ',', ' '),
            ];
        }

        return $counts;
    }
}
