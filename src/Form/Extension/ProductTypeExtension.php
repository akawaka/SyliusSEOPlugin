<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductType;

final class ProductTypeExtension extends AbstractSeoTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }

    protected function subjectType(): string
    {
        return 'product';
    }
}
