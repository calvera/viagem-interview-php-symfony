<?php

declare(strict_types=1);

namespace App\Report;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Statistics and text of the nightly report in the format of legacy cron/nightly_report.php
 * (sprintf pads by bytes, so lines with diacritics are shorter, as in legacy).
 *
 * @phpstan-type Stats array{owners_total: int, owners_f: int, owners_p: int, parcels_total: int, parcels_area: int,
 *     ownerships_total: int, notes_total: int, notes_24h: int, do_not_contact: int, duedate_empty: int, duedate_overdue: int,
 *     top: list<array{name: string, cnt: int}>, land_types: list<array{land_type: string, cnt: int, area: int}>}
 */
final readonly class NightlyReportBuilder
{
    public function __construct(
        private Connection $connection,
        #[Autowire('%app.name%')] private string $appName,
    ) {
    }

    /** @return Stats */
    public function stats(): array
    {
        $db = $this->connection;
        $types = $db->fetchAllKeyValue('SELECT type, COUNT(*) FROM owners GROUP BY type');

        return [
            'owners_total' => (int) $db->fetchOne('SELECT COUNT(*) FROM owners'),
            'owners_f' => (int) ($types['F'] ?? 0),
            'owners_p' => (int) ($types['P'] ?? 0),
            'parcels_total' => (int) $db->fetchOne('SELECT COUNT(*) FROM parcels'),
            'parcels_area' => (int) $db->fetchOne('SELECT SUM(area_m2) FROM parcels'),
            'ownerships_total' => (int) $db->fetchOne('SELECT COUNT(*) FROM ownerships'),
            'notes_total' => (int) $db->fetchOne('SELECT COUNT(*) FROM owner_notes'),
            'notes_24h' => (int) $db->fetchOne('SELECT COUNT(*) FROM owner_notes WHERE created_at >= NOW() - INTERVAL 1 DAY'),
            'do_not_contact' => (int) $db->fetchOne('SELECT COUNT(*) FROM owners WHERE do_not_contact = 1'),
            'duedate_empty' => (int) $db->fetchOne('SELECT COUNT(*) FROM owners WHERE duedate IS NULL'),
            'duedate_overdue' => (int) $db->fetchOne('SELECT COUNT(*) FROM owners WHERE duedate < CURDATE()'),
            'top' => array_map(static fn (array $r): array => ['name' => (string) $r['name'], 'cnt' => (int) $r['cnt']], $db->fetchAllAssociative(
                'SELECT m.name, t.cnt FROM (SELECT municipality_id, COUNT(*) AS cnt FROM owners GROUP BY municipality_id
                    ORDER BY cnt DESC, municipality_id LIMIT 10) t JOIN municipalities m ON m.id = t.municipality_id ORDER BY t.cnt DESC, t.municipality_id',
            )),
            'land_types' => array_map(
                static fn (array $r): array => ['land_type' => (string) $r['land_type'], 'cnt' => (int) $r['cnt'], 'area' => (int) $r['area']],
                $db->fetchAllAssociative('SELECT land_type, COUNT(*) AS cnt, SUM(area_m2) AS area FROM parcels GROUP BY land_type ORDER BY area DESC'),
            ),
        ];
    }

    /** @param Stats $stats */
    public function render(array $stats, string $hostname, float $duration, \DateTimeImmutable $now): string
    {
        $lines = [
            'NOČNÍ REPORT – '.$this->appName,
            'Datum: '.$now->format('d.m.Y H:i').', server: '.$hostname,
            str_repeat('=', 60),
            self::row('Vlastníci celkem', self::number($stats['owners_total'])),
            self::row('  z toho fyzické osoby', self::number($stats['owners_f'])),
            self::row('  z toho právnické osoby', self::number($stats['owners_p'])),
            self::row('Parcely', self::number($stats['parcels_total'])),
            self::row('Výměra parcel celkem (ha)', self::number($stats['parcels_area'] / 10000, 2)),
            self::row('Vlastnické vztahy', self::number($stats['ownerships_total'])),
            self::row('Poznámky celkem / za 24 h', $stats['notes_total'].' / '.$stats['notes_24h']),
            self::row('Zákaz oslovení', self::number($stats['do_not_contact'])),
            self::row('Bez termínu oslovení', self::number($stats['duedate_empty'])),
            self::row('Termín oslovení po splatnosti', self::number($stats['duedate_overdue'])),
            '',
            'Obce s nejvíce vlastníky:',
        ];
        foreach ($stats['top'] as $i => $top) {
            $lines[] = \sprintf('%3d. %-45s %8d', $i + 1, $top['name'], $top['cnt']);
        }
        $lines[] = '';
        $lines[] = 'Druhy pozemků:';
        foreach ($stats['land_types'] as $type) {
            $lines[] = \sprintf('  %-35s %9d parcel %14s ha', mb_ucfirst($type['land_type']), $type['cnt'], self::number($type['area'] / 10000, 2));
        }
        $lines[] = '';
        $lines[] = 'Doba výpočtu: '.$duration.' s';

        return implode(\PHP_EOL, $lines).\PHP_EOL;
    }

    private static function row(string $label, string $value): string
    {
        return \sprintf('%-40s %15s', $label, $value);
    }

    private static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
