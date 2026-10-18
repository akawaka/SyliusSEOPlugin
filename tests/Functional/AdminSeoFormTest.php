<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadata;

final class AdminSeoFormTest extends FunctionalTestCase
{
    public function testProductEditFormExposesSeoFields(): void
    {
        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/products/' . $this->firstProduct()->getId() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('input[name$="[akawaka_seo][en_US][metaTitle]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('textarea[name$="[akawaka_seo][en_US][metaDescription]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('.accordion .accordion-item .accordion-collapse.show input[name$="[akawaka_seo][en_US][metaTitle]"]')->count());
    }

    public function testSerpPreviewShowsTheStorefrontUrlOfTheProduct(): void
    {
        $product = $this->firstProduct();
        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/products/' . $product->getId() . '/edit');

        self::assertStringEndsWith($this->productUrl($product), trim($crawler->filter('.collapse.show [data-seo-serp-url]')->text()));
    }

    public function testSubmittingTheProductFormStoresSeoMetadata(): void
    {
        $this->logInAsAdministrator();
        $product = $this->firstProduct();
        $productId = (int) $product->getId();
        $crawler = $this->client->request('GET', '/admin/products/' . $productId . '/edit');

        $form = $crawler->filter('form[name="sylius_admin_product"]')->form();
        $form['sylius_admin_product[akawaka_seo][en_US][metaTitle]'] = 'Stored title';
        $form['sylius_admin_product[akawaka_seo][en_US][metaDescription]'] = 'Stored description';
        $form['sylius_admin_product[akawaka_seo][en_US][robotsIndex]']->untick();
        $this->client->submit($form);

        self::assertResponseRedirects();

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(SeoMetadata::class)->findOneBy(['subjectType' => 'product', 'subjectId' => $productId, 'locale' => 'en_US']);

        self::assertInstanceOf(SeoMetadata::class, $stored);
        self::assertSame('Stored title', $stored->getMetaTitle());
        self::assertSame('Stored description', $stored->getMetaDescription());
        self::assertFalse($stored->isRobotsIndex());

        $crawler = $this->client->request('GET', $this->productUrl($this->firstProduct()));
        self::assertSame('Stored title', $crawler->filter('title')->text());
    }

    public function testSubmittingTheTaxonFormStoresSeoMetadata(): void
    {
        $this->logInAsAdministrator();
        $taxon = static::getContainer()->get('sylius.repository.taxon')->findOneBy(['code' => 't_shirts']);
        \assert(null !== $taxon);
        $taxonId = (int) $taxon->getId();

        $crawler = $this->client->request('GET', '/admin/taxons/' . $taxonId . '/edit');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString('/en_US/taxons/' . $taxon->getTranslation('en_US')->getSlug(), $crawler->filter('.collapse.show [data-seo-serp-url]')->text());

        $form = $crawler->filter('form[name="sylius_admin_taxon"]')->form();
        $form['sylius_admin_taxon[akawaka_seo][en_US][metaTitle]'] = 'Taxon SEO title';
        $this->client->submit($form);

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(SeoMetadata::class)->findOneBy(['subjectType' => 'taxon', 'subjectId' => $taxonId, 'locale' => 'en_US']);

        self::assertInstanceOf(SeoMetadata::class, $stored);
        self::assertSame('Taxon SEO title', $stored->getMetaTitle());
    }

    public function testInvalidUrlsAreRejectedAndNothingIsStored(): void
    {
        $this->logInAsAdministrator();
        $productId = (int) $this->firstProduct()->getId();
        $crawler = $this->client->request('GET', '/admin/products/' . $productId . '/edit');

        $form = $crawler->filter('form[name="sylius_admin_product"]')->form();
        $form['sylius_admin_product[akawaka_seo][en_US][canonicalUrl]'] = 'javascript:alert(1)';
        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('The URL must start with', $crawler->text());
        self::assertSame(0, $this->entityManager()->getRepository(SeoMetadata::class)->count([]));
    }

    public function testDeletingASubjectRemovesItsSeoRows(): void
    {
        $product = $this->firstProduct();
        $this->createSeoMetadata($product);
        $this->createSeoMetadata($product, 'fr_FR');
        $this->entityManager()->flush();

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new \Sylius\Resource\Symfony\EventDispatcher\GenericEvent($product), 'sylius.product.pre_delete');
        $dispatcher->dispatch(new \Sylius\Resource\Symfony\EventDispatcher\GenericEvent($product), 'sylius.product.post_delete');

        self::assertSame(0, $this->entityManager()->getRepository(SeoMetadata::class)->count([]));
    }
}
