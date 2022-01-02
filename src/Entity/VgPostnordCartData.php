<?php declare(strict_types = 1);

namespace Vilkas\Postnord\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Vilkas\Postnord\Repository\VgPostnordCartDataRepository")
 */
class VgPostnordCartData
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_cart_data", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_cart", type="integer", nullable=false)
     */
    private $id_cart;

    /**
     * @var string
     *
     * @ORM\Column(name="servicepointid", type="string", nullable=false)
     */
    private $servicepointid;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getIdCart(): int
    {
        return $this->id_cart;
    }

    /**
     * @param int $id_cart
     *
     * @return $this;
     */
    public function setIdCart(int $id_cart): self
    {
        $this->id_cart = $id_cart;
        return $this;
    }


    /**
     * @return string
     */
    public function getServicePointId(): string
    {
        return $this->servicepointid;
    }

    /**
     * @param string $servicepointid
     *
     * @return $this
     */
    public function setServicePointId(string $servicepointid): self
    {
        $this->servicepointid = $servicepointid;
        return $this;
    }

}