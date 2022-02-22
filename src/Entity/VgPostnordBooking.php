<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Vilkas\Postnord\Repository\VgPostnordBookingRepository")
 */
class VgPostnordBooking
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_booking", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_order", type="integer", nullable=false)
     */
    private $id_order;

    /**
     * @var VgPostnordCartData
     *
     * @ORM\ManyToOne(targetEntity="VgPostnordCartData")
     * @ORM\JoinColumn(name="id_cart_data", referencedColumnName="id_cart_data", nullable=true)
     */
    private $cart_data;

    /**
     * @var string
     *
     * @ORM\Column(name="id_booking_external", type="string", nullable=true)
     */
    private $id_booking_external;

    /**
     * @var string
     *
     * @ORM\Column(name="tracking_url", type="string", nullable=true)
     */
    private $tracking_url;

    /**
     * @var string
     *
     * Base64 encoded label PDF
     *
     * @ORM\Column(name="label_data", type="text", nullable=true)
     */
    private $label_data;

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
     * @var string
     *
     * @ORM\Column(name="id_label_external", type="string", nullable=true)
     */
    private $id_label_external;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="finalized", type="datetime")
     */
    private $finalized;

    /**
     * @var string
     *
     * @ORM\Column(name="additional_services", type="string", nullable=true)
     */
    private $additional_services;

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
     * @return VgPostnordCartData|null
     */
    public function getCartData(): ?VgPostnordCartData
    {
        return $this->cart_data;
    }

    /**
     * @param VgPostnordCartData $cart_data
     *
     * @return $this
     */
    public function setCartData(VgPostnordCartData $cart_data): self
    {
        $this->cart_data = $cart_data;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getIdBookingExternal(): ?string
    {
        return $this->id_booking_external;
    }

    /**
     * @param string $id_booking_external
     *
     * @return $this
     */
    public function setIdBookingExternal(string $id_booking_external): self
    {
        $this->id_booking_external = $id_booking_external;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTrackingUrl(): ?string
    {
        return $this->tracking_url;
    }

    /**
     * @param string|null $tracking_url
     *
     * @return $this
     */
    public function setTrackingUrl(?string $tracking_url): self
    {
        $this->tracking_url = $tracking_url;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLabelData(): ?string
    {
        return $this->label_data;
    }

    /**
     * @param string $label_data
     *
     * @return $this
     */
    public function setLabelData(string $label_data): self
    {
        $this->label_data = $label_data;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getServicepointid(): ?string
    {
        return $this->servicepointid;
    }

    /**
     * @param string|null $servicepointid
     *
     * @return $this
     */
    public function setServicepointid(?string $servicepointid): self
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

    /**
     * @return string|null
     */
    public function getIdLabelExternal(): ?string
    {
        return $this->id_label_external;
    }

    /**
     * @param string $id_label_external
     *
     * @return $this
     */
    public function setIdLabelExternal(string $id_label_external): self
    {
        $this->id_label_external = $id_label_external;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getFinalized(): ?DateTime
    {
        return $this->finalized;
    }

    /**
     * @param DateTime $finalized
     *
     * @return $this
     */
    public function setFinalized(DateTime $finalized): self
    {
        $this->finalized = $finalized;

        return $this;
    }

    /**
     * Get the value of additional_services
     *
     * @return string|null
     */
    public function getAdditionalServices(): ?string
    {
        return $this->additional_services;
    }

    /**
     * Set the value of additional_services
     *
     * @param string|null $additional_services
     *
     * @return $this
     */
    public function setAdditionalServices(?string $additional_services): self
    {
        $this->additional_services = $additional_services;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id_booking' => $this->getId(),
            'id_order' => $this->getIdOrder(),
            'cart_data' => $this->getCartData(),
            'id_booking_external' => $this->getIdBookingExternal(),
            'tracking_url' => $this->getTrackingUrl(),
            'label_data' => $this->getLabelData(),
            'servicepointid' => $this->getServicepointid(),
            'service_point_data' => $this->getServicePointData(),
            'id_label_external' => $this->getIdLabelExternal(),
            'finalized' => $this->getFinalized(),
            'additional_services' => empty($this->getAdditionalServices()) ? [] : explode(', ', $this->getAdditionalServices()),
        ];
    }

    /**
     * Only left the necessary one. Not used ones are commented out for now.
     *
     * @param $data
     */
    public function fromArray($data)
    {
        // $this->setIdOrder($data['id_order']);
        // $this->setCartData($data['cart_data']);
        // $this->setIdBookingExternal($data['id_booking_external']);
        // $this->setLabelData($data['label_data']);
        $this->setServicepointid($data['servicepointid']);
        $this->setServicePointData($data['service_point_data']);
        // $this->setIdLabelExternal($data['id_label_external']);
        // $this->setFinalized($data['finalized']);
        // $this->setTrackingUrl($data['tracking_url']);
        $this->setAdditionalServices(implode(', ', $data['additional_services']));
    }
}
