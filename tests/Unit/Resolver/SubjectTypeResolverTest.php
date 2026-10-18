<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\Resolver;

use Akawaka\SyliusSEOPlugin\Resolver\SubjectTypeResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class SubjectTypeResolverTest extends TestCase
{
    public function testResolvesTheTypeFromTheConfiguredClass(): void
    {
        $resolver = new SubjectTypeResolver([
            'product' => ['class' => ProductInterface::class, 'title_template' => 'P', 'description_template' => null],
            'taxon' => ['class' => TaxonInterface::class, 'title_template' => 'T', 'description_template' => 'D'],
        ]);

        self::assertSame('product', $resolver->resolveType($this->createStub(ProductInterface::class)));
        self::assertSame('taxon', $resolver->resolveType($this->createStub(TaxonInterface::class)));
        self::assertNull($resolver->resolveType(new \stdClass()));
        self::assertSame(['title_template' => 'T', 'description_template' => 'D'], $resolver->templatesFor('taxon'));
    }
}
