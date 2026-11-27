<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\Owner;

/**
 * CSV lines of the owners export in the legacy format: ";" separated, no escape character (RFC 4180, every
 * quote is doubled), values protected against formula injection.
 */
final class OwnerCsv
{
    public const array HEAD = ['ID', 'Typ', 'Titul', 'Jméno', 'Příjmení / název', 'Datum narození', 'IČO', 'Ulice', 'PSČ', 'Město',
        'Obec (KN)', 'Telefon', 'E-mail', 'Termín oslovení', 'Založeno'];

    /**
     * @param resource     $out
     * @param list<string> $fields
     */
    public static function line($out, array $fields): void
    {
        fputcsv($out, $fields, ';', '"', '');
    }

    /** @return list<string> */
    public static function row(Owner $owner): array
    {
        return array_map(self::safe(...), [
            (string) $owner->id,
            $owner->type->label(),
            (string) $owner->title,
            (string) $owner->firstName,
            $owner->lastName,
            (string) $owner->birthDate?->format('d.m.Y'),
            (string) $owner->ico,
            (string) $owner->street,
            (string) $owner->zip,
            (string) $owner->city,
            $owner->municipality->name,
            (string) $owner->phone,
            (string) $owner->email,
            (string) $owner->duedate?->format('d.m.Y'),
            $owner->createdAt->format('d.m.Y H:i'),
        ]);
    }

    /**
     * A value starting with =, +, -, @, tab or CR gets a leading apostrophe so a spreadsheet does not run it
     * as a formula; a phone number like "+420 123 456 789" stays as it is (legacy csv_safe()).
     */
    public static function safe(string $value): string
    {
        if (!preg_match('/^[=+\-@\t\r]/', $value) || preg_match('/^\+?[0-9 ()-]+$/', $value)) {
            return $value;
        }

        return "'".$value;
    }
}
