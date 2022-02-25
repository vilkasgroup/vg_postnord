<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class VgPostnordParcelType extends TranslatorAwareType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('weight', TextType::class, [
                'label' => $this->trans('Weight', 'Modules.Vgpostnord.Admin')
            ])
            ->add('remove_parcel_button', ButtonType::class, [
                'attr' => ['class' => 'vg-postnord-remove-parcel btn btn-primary'],
                'label' => $this->trans('Remove parcel', 'Modules.Vgpostnord.Admin')
            ])
        ;
    }
}
