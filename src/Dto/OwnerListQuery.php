<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/** GET parameters of the owners list and CSV export (legacy names). */
final readonly class OwnerListQuery
{
    public const array SORTS = ['o.created_at'];

    /** Same minimum as the municipality autocomplete (legacy OPENQUESTIONS.md, question 7). */
    public const int MUNICIPALITY_MIN_LENGTH = 2;

    /** Municipality id from the link in the municipalities list, digits only. */
    #[SerializedName('f_municipality_id')]
    public string $municipalityId;

    /** Part of the municipality name; shorter than the minimum it is ignored. */
    #[SerializedName('f_municipality')]
    public string $municipality;

    /** Text of the municipality field as entered (kept when ignored as too short). */
    public string $municipalityInput;

    #[SerializedName('f_type')]
    public string $type;

    #[SerializedName('f_dnc')]
    public string $dnc;

    /** Eight digits search the IČO exactly, the search text is then empty. */
    public string $ico;

    public string $search;
    public string $sort;
    public string $dir;
    public int $page;

    /** Export only: include do-not-contact owners. */
    public bool $all;

    public function __construct(
        mixed $municipalityId = '',
        mixed $municipality = '',
        mixed $type = '',
        mixed $dnc = '',
        mixed $search = '',
        mixed $sort = '',
        mixed $dir = '',
        mixed $page = 1,
        mixed $all = '',
    ) {
        $this->municipalityId = QueryParam::digits($municipalityId);
        $this->municipalityInput = QueryParam::text($municipality);
        $this->municipality = mb_strlen($this->municipalityInput) >= self::MUNICIPALITY_MIN_LENGTH ? $this->municipalityInput : '';
        $this->type = QueryParam::text($type);
        $this->dnc = QueryParam::text($dnc);
        $ico = \is_string($search) && preg_match('/^\s*(\d{8})\s*$/', $search, $m) ? $m[1] : '';
        $this->ico = $ico;
        $this->search = $ico === '' ? QueryParam::text($search) : '';
        $this->sort = QueryParam::choice($sort, self::SORTS);
        $this->dir = QueryParam::dir($dir);
        $this->page = QueryParam::page($page);
        $this->all = \is_string($all) && $all !== '' && $all !== '0';
    }

    /** The "V obci" column: filter by municipality id or name. */
    public function hasMunicipalityFilter(): bool
    {
        return $this->municipalityId !== '' || $this->municipality !== '';
    }

    public function municipalityNote(): ?string
    {
        return $this->municipalityInput !== '' && $this->municipality === '' ? 'zadejte aspoň '.self::MUNICIPALITY_MIN_LENGTH.' znaky' : null;
    }
}
