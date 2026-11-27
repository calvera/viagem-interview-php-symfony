<?php

declare(strict_types=1);

namespace App\Export;

use App\Dto\OwnerListQuery;
use App\Repository\OwnerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owners of the filtered list as CSV (legacy owners_export.php): BOM, title line, head, rows; without "all"
 * the do-not-contact owners are left out and counted on the last line.
 */
final readonly class OwnerExport
{
    public const int LIMIT = 20000;

    public function __construct(private OwnerRepository $owners, private EntityManagerInterface $em)
    {
    }

    /**
     * @param resource $out
     *
     * @return int number of exported owners
     */
    public function write($out, OwnerListQuery $query, string $user): int
    {
        $query = $query->withoutSort();
        $skipped = $query->all ? 0 : $this->owners->countDoNotContact($query);
        fwrite($out, "\xEF\xBB\xBF");
        OwnerCsv::line($out, ['Export vlastníků', date('d.m.Y H:i'), $user]);
        OwnerCsv::line($out, OwnerCsv::HEAD);
        $exported = 0;
        foreach ($this->owners->exportRows($query, self::LIMIT) as $owner) {
            OwnerCsv::line($out, OwnerCsv::row($owner));
            if (++$exported % 1000 === 0) {
                $this->em->clear();
            }
        }
        if ($skipped > 0) {
            OwnerCsv::line($out, []);
            OwnerCsv::line($out, ['Vynecháno '.$skipped.' vlastníků se zákazem oslovení.']);
        }

        return $exported;
    }
}
