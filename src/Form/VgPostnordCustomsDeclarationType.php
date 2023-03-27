<?php

declare(strict_types=1);

namespace Vilkas\Postnord\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class VgPostnordCustomsDeclarationType extends TranslatorAwareType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $currencies = \Currency::getCurrencies(false, true, true);

        $currency_choices = [];
        foreach ($currencies as $currency) {
            $currency_choices[$currency['iso_code']] = $currency['iso_code'];
        }

        $categories = [
            '' => null,
            $this->trans('Gift', 'Modules.Vgpostnord.Admin') => 'GIFT',
            $this->trans('Documents', 'Modules.Vgpostnord.Admin') => 'DOCUMENTS',
            $this->trans('Returns', 'Modules.Vgpostnord.Admin') => 'RETURNS',
            $this->trans('Commercial Sample', 'Modules.Vgpostnord.Admin') => 'COMMERCIALS',
            $this->trans('Others', 'Modules.Vgpostnord.Admin') => 'OTHERS',
        ];

        // wish we had a ??: operator or something
        // grab variable from data if it's found and truthy, otherwise use whatever is in first index of choices
        $currency = !empty($options['data']['customs_declaration_data']['currency'])
            ? $options['data']['customs_declaration_data']['currency']
            : reset($currency_choices);
        $category = !empty($options['data']['customs_declaration_data']['categoryOfItem'])
            ? $options['data']['customs_declaration_data']['categoryOfItem']
            : reset($categories);

        $builder
            ->add('currency', ChoiceType::class, [
                'choices' => $currency_choices,
                'data' => $currency
            ])
            ->add('categoryOfItem', ChoiceType::class, [
                'choices' => $categories,
                'data' => $category
            ])
            ->add('categoryExplanation', TextType::class, [
                'label' => $this->trans('Explanation', 'Modules.Vgpostnord.Admin'),
                'data' => $options['data']['customs_declaration_data']['categoryExplanation'] ?? '',
                'required' => false
            ])
            ->add('add_detailed_description', ButtonType::class, [
                'attr' => ['class' => 'vg-postnord-add-detailed-description btn btn-primary'],
                'label' => $this->trans('Add content line', 'Modules.Vgpostnord.Admin')
            ])
            ->add('detailed_description', CollectionType::class, [
                'data' => $options['data']['detailed_description'],
                'label' => $this->trans('Detailed description', 'Modules.Vgpostnord.Admin'),
                'entry_type' => VgPostnordDetailedDescriptionType::class,
                'allow_extra_fields' => true,
                'allow_add' => true,
                'allow_delete' => true
            ])
        ;
    }
}
