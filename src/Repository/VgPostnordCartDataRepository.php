<?php

namespace Vilkas\Postnord\Repository;

use Doctrine\ORM\EntityRepository;

use Vilkas\Postnord\Entity\VgPostnordCartData;

/**
 * Repo for cart data
 */
class VgPostnordCartDataRepository extends EntityRepository
{
    /**
     * set or update servicepointid for cart data
     */
    public function upsertCartServicePointId(int $id_cart, string $servicepointid): VgPostnordCartData
    {
        $manager = $this->getEntityManager();
        $data = $this->_findOrNew($id_cart);
        $data->setServicePointId($servicepointid);
        $manager->persist($data);
        $manager->flush();

        return $data;
    }

    /**
     * try to find or return a new cart data object if not found
     */
    private function _findOrNew(int $id_cart): VgPostnordCartData
    {
        $manager = $this->getEntityManager();

        // try to first find the stock and the update it
        $search = [
            'id_cart' => $id_cart,
        ];
        $obj = $this->findOneBy($search);

        // or create new
        if (!$obj) {
            $obj = new VgPostnordCartData();
            $obj->setIdCart($id_cart);
            $manager->persist($obj);
            $manager->flush();
        }

        return $obj;
    }

}
