<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/** GET parameters of the parcels list (legacy names). */
final readonly class ParcelListQuery
{
    public const array SORTS = ['p.cadastral_area', 'p.area_m2'];

    /** Municipality id from the link in the municipalities list, digits only. */
    #[SerializedName('f_municipality_id')]
    public string $municipalityId;

    /** Part of the municipality name. */
    #[SerializedName('f_municipality')]
    public string $municipality;

    #[SerializedName('f_land_type')]
    public string $landType;

    public string $search;
    public string $sort;
    public string $dir;
    public int $page;

    public function __construct(
        mixed $municipalityId = '',
        mixed $municipality = '',
        mixed $landType = '',
        mixed $search = '',
        mixed $sort = '',
        mixed $dir = '',
        mixed $page = 1,
    ) {
        $this->municipalityId = QueryParam::digits($municipalityId);
        $this->municipality = QueryParam::text($municipality);
        $this->landType = QueryParam::text($landType);
        $this->search = QueryParam::text($search);
        $this->sort = QueryParam::choice($sort, self::SORTS);
        $this->dir = QueryParam::dir($dir);
        $this->page = QueryParam::page($page);
    }
}
