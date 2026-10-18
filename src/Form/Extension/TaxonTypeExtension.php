<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Form\Extension;

use Sylius\Bundle\TaxonomyBundle\Form\Type\TaxonType;

final class TaxonTypeExtension extends AbstractSeoTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [TaxonType::class];
    }

    protected function subjectType(): string
    {
        return 'taxon';
    }
}
