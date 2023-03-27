<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

class VgPostnordParcelType extends TranslatorAwareType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('grossWeight', NumberType::class, [
                'label' => $this->trans('Weight (kg)', 'Modules.Vgpostnord.Admin'),
                'required' => false
            ])->add('height', NumberType::class, [
                'label' => $this->trans('Height (cm)', 'Modules.Vgpostnord.Admin'),
                'required' => false
            ])->add('width', NumberType::class, [
                'label' => $this->trans('Width (cm)', 'Modules.Vgpostnord.Admin'),
                'required' => false
            ])->add('length', NumberType::class, [
                'label' => $this->trans('Length (cm)', 'Modules.Vgpostnord.Admin'),
                'required' => false
            ])
            ->add('remove_parcel_button', ButtonType::class, [
                'attr' => ['class' => 'vg-postnord-remove-parcel btn btn-primary'],
                'label' => $this->trans('Remove parcel', 'Modules.Vgpostnord.Admin')
            ])
        ;
    }
}
