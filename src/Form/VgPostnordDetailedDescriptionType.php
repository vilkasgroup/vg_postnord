<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;

class VgPostnordDetailedDescriptionType extends TranslatorAwareType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // TODO: should probably validate more than content length
        //       - should this be validated if customs declaration checkbox is not selected?
        $builder
            ->add('content', TextType::class, [
                'label' => $this->trans('Detailed description of the content', 'Modules.Vgpostnord.Admin'),
                'required' => true,
                'constraints' => [new Length(['min' => 1, 'max' => 35])]
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'required' => true
            ])
            ->add('grossWeight', NumberType::class, [
                'label' => $this->trans('Weight (kg)', 'Modules.Vgpostnord.Admin'),
                'required' => true
            ])
            ->add('value', NumberType::class, [
                'label' => $this->trans('Value', 'Modules.Vgpostnord.Admin'),
                'required' => true
            ])
            ->add('tariffNumber', TextType::class, [
                'label' => $this->trans('HS tariff number', 'Modules.Vgpostnord.Admin'),
                'required' => true
            ])
            ->add('countryCode', TextType::class, [
                'label' => $this->trans('Country code (ISO 3166)', 'Modules.Vgpostnord.Admin'),
                'required' => true
            ])
            ->add('remove_detailed_description', ButtonType::class, [
                'attr' => ['class' => 'vg-postnord-remove-detailed-description btn btn-primary'],
                'label' => $this->trans('Remove content line', 'Modules.Vgpostnord.Admin')
            ])
        ;
    }
}
