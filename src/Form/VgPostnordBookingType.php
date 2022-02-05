<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShopBundle\Form\Admin\Type\CommonAbstractType;
use PrestaShopBundle\Form\Admin\Type\Material\MaterialChoiceTableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatorInterface;

class VgPostnordBookingType extends CommonAbstractType
{

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     */
    // public function __construct(TranslatorInterface $translator, array $locales)
    // {

    //     parent::__construct($translator, $locales);
    // }
    // $this->trans('Tracking URL', 'Modules.Vgpostnord.Admin')
    // $this->trans('Additional Services', 'Modules.Vgpostnord.Admin')
    // $this->trans('Enable additional services for the shipment', 'Modules.Vgpostnord.Admin')
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('tracking_url', TextType::class, [
                'label' => 'Tracking URL',
                ])
            ->add('additional_services', MaterialChoiceTableType::class, [
                'label' => 'Additional Services',
                'help' => 'Additional Services',
                'choices' => [
                    'Test 1' => 'A1',
                    'Test 2' => 'A2',
                    'Test 3' => 'A3'
                ],
            ]);
    }
}
