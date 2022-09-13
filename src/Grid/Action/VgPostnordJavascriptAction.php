<?php

namespace Vilkas\Postnord\Grid\Action;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\AbstractBulkAction;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class VgPostnordJavascriptAction extends AbstractBulkAction
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return "javascript";
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired([
                "function",
                "modal_id",
            ])
            ->setDefined([
                "route"
            ])
            ->setAllowedTypes("function", "string")
            ->setAllowedTypes("modal_id", "string")
            ->setAllowedTypes("route", "string")
        ;
    }
}
