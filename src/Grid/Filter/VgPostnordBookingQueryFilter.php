<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Grid\Filter;

use PrestaShop\PrestaShop\Core\Search\Filters;

use Vilkas\Postnord\Grid\Definition\Factory\VgPostnordBookingGridDefinitionFactory;

class VgPostnordBookingQueryFilter extends Filters
{
    protected $filterId = VgPostnordBookingGridDefinitionFactory::GRID_ID;

    /**
     * {@inheritdoc}
     */
    public static function getDefaults(): array
    {
        return [
            'limit' => 10,
            'offset' => 0,
            'orderBy' => 'id_booking',
            'sortOrder' => 'asc',
            'filters' => [],
        ];
    }
}
