<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Grid\Query\Builder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

class VgPostnordBooking extends AbstractDoctrineQueryBuilder
{
    /**
     * @var DoctrineSearchCriteriaApplicatorInterface
     */
    private $searchCriteriaApplicator;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     */

    public function __construct(Connection $connection, string $dbPrefix, DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator)
    {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
    }

    /**
     * Search queries
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        // base query
        $qb = $this->getBaseQuery();

        // order and pagination
        $qb->select('vpb.id_booking, vpb.tracking_url, vpb.servicepointid, vpb.additional_services')
            ->orderBy(
                $searchCriteria->getOrderBy(),
                $searchCriteria->getOrderWay()
            )
            ->setFirstResult($searchCriteria->getOffset())
            ->setMaxResults($searchCriteria->getLimit());

        $this->searchCriteriaApplicator
            ->applySorting($searchCriteria, $qb)
            ->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    /**
     * Get the number of object
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        // base query
        $qb = $this->getBaseQuery($searchCriteria);

        // just select for now
        $qb->select('COUNT(id_booking)');

        return $qb;
    }

    /**
     * Base query for the whole Grid
     */
    private function getBaseQuery(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from("{$this->dbPrefix}vg_postnord_booking", 'vpb')
            // Not sure if it's necessary
            // ->join(
            //     'vpb',
            //     "{$this->dbPrefix}vg_postnord_cart_data",
            //     'vpc',
            //     'vpb.id_cart_data = vpc.id_cart_data'
            // )
        ;
    }
}
