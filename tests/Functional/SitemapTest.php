<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

final class SitemapTest extends FunctionalTestCase
{
    public function testIndexListsProductAndTaxonSections(): void
    {
        $this->client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/xml', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('/sitemap-product-1.xml', $xml);
        self::assertStringContainsString('/sitemap-taxon-1.xml', $xml);
    }

    public function testProductSectionContainsProductsAndSkipsNoIndexOnes(): void
    {
        $product = $this->firstProduct();
        $url = $this->productUrl($product);

        $this->client->request('GET', '/sitemap-product-1.xml');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($url, (string) $this->client->getResponse()->getContent());

        $this->createSeoMetadata($product)->setRobotsIndex(false);
        $this->entityManager()->flush();

        $this->client->request('GET', '/sitemap-product-1.xml');
        self::assertStringNotContainsString('<loc>http://localhost' . $url . '</loc>', (string) $this->client->getResponse()->getContent());
    }

    public function testUnknownSectionIsNotFound(): void
    {
        $this->client->request('GET', '/sitemap-product-999.xml');

        self::assertResponseStatusCodeSame(404);
    }
}
