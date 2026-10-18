<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Symfony\Component\DomCrawler\Crawler;

final class ProductHeadTest extends FunctionalTestCase
{
    public function testDefaultTemplatesAreUsedWithoutStoredMetadata(): void
    {
        $product = $this->firstProduct();
        $crawler = $this->client->request('GET', $this->productUrl($product));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $product->getName(), $crawler->filter('title')->text());
        self::assertSame('index,follow', $this->attribute($crawler, 'meta[name="robots"]', 'content'));
        self::assertStringEndsWith($this->productUrl($product), (string) $this->attribute($crawler, 'link[rel="canonical"]', 'href'));
        self::assertSame('product', $this->attribute($crawler, 'meta[property="og:type"]', 'content'));
        self::assertSame('en-US', $this->attribute($crawler, 'link[rel="alternate"]', 'hreflang'));
        self::assertNotNull($this->attribute($crawler, 'meta[name="twitter:card"]', 'content'));
    }

    public function testStoredMetadataOverridesTemplates(): void
    {
        $product = $this->firstProduct();
        $metadata = $this->createSeoMetadata($product);
        $metadata->setMetaTitle('Custom SEO title');
        $metadata->setMetaDescription('Custom SEO description');
        $metadata->setCanonicalUrl('https://example.com/canonical');
        $metadata->setRobotsIndex(false);
        $metadata->setRobotsFollow(false);
        $metadata->setOgImage('https://example.com/og.png');
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', $this->productUrl($product));

        self::assertResponseIsSuccessful();
        self::assertSame('Custom SEO title', $crawler->filter('title')->text());
        self::assertSame('Custom SEO description', $this->attribute($crawler, 'meta[name="description"]', 'content'));
        self::assertSame('noindex,nofollow', $this->attribute($crawler, 'meta[name="robots"]', 'content'));
        self::assertSame('https://example.com/canonical', $this->attribute($crawler, 'link[rel="canonical"]', 'href'));
        self::assertSame('Custom SEO title', $this->attribute($crawler, 'meta[property="og:title"]', 'content'));
        self::assertSame('https://example.com/og.png', $this->attribute($crawler, 'meta[property="og:image"]', 'content'));
    }

    public function testJsonLdDescribesTheProduct(): void
    {
        $product = $this->firstProduct();
        $crawler = $this->client->request('GET', $this->productUrl($product));

        $nodes = json_decode($crawler->filter('script[type="application/ld+json"]')->text(null, false), true, 512, \JSON_THROW_ON_ERROR);
        $types = array_column($nodes, '@type');

        self::assertSame(['Organization', 'BreadcrumbList', 'Product'], $types);
        $productNode = $nodes[2];
        self::assertSame($product->getName(), $productNode['name']);
        self::assertSame($product->getCode(), $productNode['sku']);
        self::assertContains($productNode['offers']['@type'], ['Offer', 'AggregateOffer']);
        self::assertNotEmpty($productNode['offers']['priceCurrency']);
    }

    public function testPagesWithoutSubjectAreUntouched(): void
    {
        $crawler = $this->client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
    }

    private function attribute(Crawler $crawler, string $selector, string $attribute): ?string
    {
        $node = $crawler->filter($selector);

        return 0 === $node->count() ? null : $node->attr($attribute);
    }
}
