<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
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
        ;
    }
}
