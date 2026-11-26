<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/** GET parameters of the municipalities list (legacy names). */
final readonly class MunicipalityListQuery
{
    public const array SORTS = ['code', 'name', 'district', 'region'];

    #[SerializedName('f_district')]
    public string $district;

    #[SerializedName('f_region')]
    public string $region;

    public string $search;
    public string $sort;
    public string $dir;
    public int $page;

    public function __construct(mixed $district = '', mixed $region = '', mixed $search = '', mixed $sort = '', mixed $dir = '', mixed $page = 1)
    {
        $this->district = QueryParam::text($district);
        $this->region = QueryParam::text($region);
        $this->search = QueryParam::text($search);
        $this->sort = QueryParam::choice($sort, self::SORTS);
        $this->dir = QueryParam::dir($dir);
        $this->page = QueryParam::page($page);
    }
}
