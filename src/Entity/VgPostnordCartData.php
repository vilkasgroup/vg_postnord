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
     * @var int
     *
     * @ORM\Column(name="id_order", type="integer", nullable=true)
     */
    private $id_order;

    /**
     * @var string
     *
     * @ORM\Column(name="servicepointid", type="string", nullable=true)
     */
    private $servicepointid;

    /**
     * @var string
     *
     * @ORM\Column(name="service_point_data", type="text", nullable=true)
     */
    private $service_point_data;


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
     * @return $this
     */
    public function setIdCart(int $id_cart): self
    {
        $this->id_cart = $id_cart;
        return $this;
    }


    /**
     * @return int
     */
    public function getIdOrder(): int
    {
        return $this->id_order;
    }

    /**
     * @param int $id_order
     *
     * @return $this
     */
    public function setIdOrder(int $id_order): self
    {
        $this->id_order = $id_order;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getServicePointId(): ?string
    {
        return $this->servicepointid;
    }

    /**
     * @param string|null $servicepointid
     *
     * @return $this
     */
    public function setServicePointId(?string $servicepointid): self
    {
        $this->servicepointid = $servicepointid;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getServicePointData(): ?string
    {
        return $this->service_point_data;
    }

    /**
     * @param string|null $service_point_data
     *
     * @return $this
     */
    public function setServicePointData(?string $service_point_data): self
    {
        $this->service_point_data = $service_point_data;

        return $this;
    }
}
