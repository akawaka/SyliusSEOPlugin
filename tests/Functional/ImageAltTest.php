<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\ImageAlt;

final class ImageAltTest extends FunctionalTestCase
{
    public function testAltTextsAreEditedFromTheAdminAndShownOnTheStorefront(): void
    {
        $product = $this->firstProduct();
        $image = $product->getImages()->first();
        self::assertNotFalse($image);

        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/image-alts?type=product&locale=en_US');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-image-alt-row="' . $image->getId() . '"]')->count());

        $form = $crawler->filter('form[data-image-alt-form]')->form();
        $form['alt[' . $image->getId() . ']'] = 'Photo "de face" du produit';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(ImageAlt::class)->findOneBy(['imagePath' => $image->getPath(), 'locale' => 'en_US']);
        self::assertInstanceOf(ImageAlt::class, $stored);
        self::assertSame('Photo "de face" du produit', $stored->getAlt());

        $crawler = $this->client->request('GET', $this->productUrl($this->firstProduct()));
        $alts = $crawler->filter('img')->each(static fn ($img): ?string => $img->attr('alt'));
        self::assertContains('Photo "de face" du produit', $alts);
    }

    public function testAuditReportsImagesWithoutAlt(): void
    {
        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit');

        self::assertGreaterThan(0, $crawler->filter('[data-audit-issue="image_without_alt"]')->count());
    }

    public function testSavingRequiresAValidCsrfToken(): void
    {
        $this->logInAsAdministrator();
        $this->client->request('POST', '/admin/seo/image-alts', ['_token' => 'invalid', 'alt' => [1 => 'x']]);

        self::assertResponseStatusCodeSame(403);
    }
}
