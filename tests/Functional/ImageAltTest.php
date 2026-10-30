<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\ImageAlt;

final class ImageAltTest extends FunctionalTestCase
{
    public function testAltTextsAreEditedFromTheAuditAndShownOnTheStorefront(): void
    {
        $product = $this->firstProduct();
        $image = $product->getImages()->first();
        self::assertNotFalse($image);

        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit?locale=en_US&limit=50');
        self::assertResponseIsSuccessful();
        $input = $crawler->filter('[data-audit-image-alt][data-type="product"][data-id="' . $image->getId() . '"]');
        self::assertCount(1, $input);

        self::assertCount(
            $crawler->filter('[data-audit-image-alt]')->count(),
            $crawler->filter('.input-group > [data-audit-image-alt] + [data-audit-image-alt-fill]'),
        );
        $fill = $input->nextAll()->filter('[data-audit-image-alt-fill]');
        self::assertCount(1, $fill);
        self::assertStringContainsString((string) $product->getName(), (string) $fill->attr('data-name'));

        $this->client->request('POST', '/admin/seo/audit/image-alt', [
            '_token' => $crawler->filter('[data-audit-image-alt-root]')->attr('data-token'),
            'type' => 'product',
            'id' => $image->getId(),
            'locale' => 'en_US',
            'alt' => 'Photo "de face" du produit',
        ]);
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(ImageAlt::class)->findOneBy(['imagePath' => $image->getPath(), 'locale' => 'en_US']);
        self::assertInstanceOf(ImageAlt::class, $stored);
        self::assertSame('Photo "de face" du produit', $stored->getAlt());

        $crawler = $this->client->request('GET', $this->productUrl($this->firstProduct()));
        $alts = $crawler->filter('img')->each(static fn ($img): ?string => $img->attr('alt'));
        self::assertContains('Photo "de face" du produit', $alts);

        $crawler = $this->client->request('GET', '/admin/seo/audit?locale=en_US&limit=50');
        self::assertCount(0, $crawler->filter('[data-audit-image-alt][data-type="product"][data-id="' . $image->getId() . '"]'));
    }

    public function testAuditReportsImagesWithoutAlt(): void
    {
        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit?limit=50');

        self::assertGreaterThan(0, $crawler->filter('[data-audit-issue="image_without_alt"]')->count());
    }

    public function testSavingRequiresAValidCsrfToken(): void
    {
        $this->logInAsAdministrator();
        $this->client->request('POST', '/admin/seo/audit/image-alt', ['_token' => 'invalid', 'type' => 'product', 'id' => 1, 'locale' => 'en_US', 'alt' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }
}
