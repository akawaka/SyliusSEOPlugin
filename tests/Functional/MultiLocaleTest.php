<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;

final class MultiLocaleTest extends FunctionalTestCase
{
    private ?ChannelInterface $channel = null;

    private ?LocaleInterface $locale = null;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = static::getContainer()->get('sylius.repository.channel')->findOneBy([]);
        $locale = static::getContainer()->get('sylius.repository.locale')->findOneBy(['code' => 'de_DE']);
        \assert($channel instanceof ChannelInterface && $locale instanceof LocaleInterface);

        $this->channel = $channel;
        $this->locale = $locale;
        $channel->addLocale($locale);
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager()->clear();
        $channel = static::getContainer()->get('sylius.repository.channel')->find($this->channel->getId());
        $locale = static::getContainer()->get('sylius.repository.locale')->find($this->locale->getId());
        $channel->removeLocale($locale);
        $this->entityManager()->flush();

        parent::tearDown();
    }

    public function testProductPageListsAnAlternateForEveryChannelLocale(): void
    {
        $crawler = $this->client->request('GET', $this->productUrl($this->firstProduct()));

        $hreflangs = $crawler->filter('link[rel="alternate"]')->each(static fn ($link): ?string => $link->attr('hreflang'));
        sort($hreflangs);

        self::assertSame(['de-DE', 'en-US'], $hreflangs);
    }

    public function testSitemapEntriesCarryTheAlternatesAndSkipNoIndexLocalesOnly(): void
    {
        $product = $this->firstProduct();
        $metadata = $this->createSeoMetadata($product, 'de_DE');
        $metadata->setRobotsIndex(false);
        $this->entityManager()->flush();

        $this->client->request('GET', '/sitemap-product-1.xml');
        self::assertResponseIsSuccessful();
        $xml = (string) $this->client->getResponse()->getContent();

        $slug = $product->getTranslation('en_US')->getSlug();
        self::assertStringContainsString('<loc>http://localhost/en_US/products/' . $slug . '</loc>', $xml);
        self::assertStringNotContainsString('<loc>http://localhost/de_DE/products/' . $slug . '</loc>', $xml);
        self::assertStringContainsString('hreflang="de-DE" href="http://localhost/de_DE/products/' . $slug . '"', $xml);
        self::assertStringContainsString('hreflang="en-US" href="http://localhost/en_US/products/' . $slug . '"', $xml);
    }
}
