<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\Redirect;
use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/** Runs only when the CMS plugin is installed next to the SEO plugin. */
final class CmsIntegrationTest extends FunctionalTestCase
{
    private const PAGE = 'Akawaka\SyliusCMSPlugin\Entity\Page';

    protected function setUp(): void
    {
        if (!class_exists(self::PAGE)) {
            self::markTestSkipped('The Akawaka CMS plugin is not installed.');
        }

        parent::setUp();
        $this->removePages();
    }

    protected function tearDown(): void
    {
        $this->removePages();
        $this->entityManager()->createQuery('DELETE FROM ' . Redirect::class)->execute();

        parent::tearDown();
    }

    public function testPublishedPagesAreInTheSitemap(): void
    {
        $this->createPage('about-us', 'published');
        $this->createPage('draft-page', 'draft');

        $this->client->request('GET', '/sitemap.xml');
        self::assertStringContainsString('/sitemap-page-1.xml', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/sitemap-page-1.xml');
        self::assertResponseIsSuccessful();
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('/en_US/pages/about-us', $xml);
        self::assertStringNotContainsString('draft-page', $xml);
    }

    public function testPageGetsStructuredDataWithoutDuplicatedMetaTags(): void
    {
        $this->createPage('about-us', 'published');

        $crawler = $this->client->request('GET', '/en_US/pages/about-us');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('link[rel="canonical"]'));
        $nodes = json_decode($crawler->filter('script[type="application/ld+json"]')->text(null, false), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['Organization', 'BreadcrumbList'], array_column($nodes, '@type'));
    }

    public function testAuditCoversPages(): void
    {
        $this->createPage('about-us', 'published');

        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('About us', $crawler->filter('table')->text());
    }

    public function testChangingAPageSlugCreatesARedirect(): void
    {
        $page = $this->createPage('about-us', 'published');
        $page->getTranslation('en_US')->setSlug('about');
        $this->entityManager()->flush();

        $redirect = $this->entityManager()->getRepository(Redirect::class)->findOneBy(['sourcePath' => '/en_US/pages/about-us']);

        self::assertInstanceOf(Redirect::class, $redirect);
        self::assertSame('/en_US/pages/about', $redirect->getTargetUrl());
        self::assertSame(CmsPages::SHOW_ROUTE, 'akawaka_cms_shop_page_show');
    }

    private function createPage(string $slug, string $status): object
    {
        $class = self::PAGE;
        $enum = 'Akawaka\SyliusCMSPlugin\Enum\PageStatus';
        $channel = static::getContainer()->get('sylius.repository.channel')->findOneBy([]);
        \assert($channel instanceof ChannelInterface);

        $page = new $class();
        $page->setCode('seo_test_' . $slug);
        $page->setStatus($enum::from($status));
        $page->addChannel($channel);
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $translation = $page->getTranslation('en_US');
        $translation->setTitle('About us');
        $translation->setSlug($slug);
        $translation->setBlocks([]);

        $this->entityManager()->persist($page);
        $this->entityManager()->flush();

        return $page;
    }

    private function removePages(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->entityManager();
        $em->clear();
        foreach ($em->getRepository(self::PAGE)->findAll() as $page) {
            if (str_starts_with((string) $page->getCode(), 'seo_test_')) {
                $em->remove($page);
            }
        }
        $em->flush();
    }
}
