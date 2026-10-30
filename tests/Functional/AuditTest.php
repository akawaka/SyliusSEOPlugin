<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadata;

final class AuditTest extends FunctionalTestCase
{
    public function testAuditPageListsIssuesOfTheCatalog(): void
    {
        $product = $this->firstProduct();
        $metadata = $this->createSeoMetadata($product);
        $metadata->setMetaTitle('Short');
        $metadata->setRobotsIndex(false);
        $this->entityManager()->flush();

        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit?limit=50');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-audit-issue="title_too_short"]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-audit-issue="no_index"]')->count());
        self::assertStringContainsString((string) $product->getName(), $crawler->filter('table')->text());
    }

    public function testTitleAndRobotsIssuesAreFixedFromTheAudit(): void
    {
        $product = $this->firstProduct();
        $metadata = $this->createSeoMetadata($product);
        $metadata->setMetaTitle('Short');
        $metadata->setRobotsIndex(false);
        $this->entityManager()->flush();
        $id = (int) $product->getId();

        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit?locale=en_US&limit=50');
        $token = $crawler->filter('[data-audit-image-alt-root]')->attr('data-token');
        self::assertGreaterThan(0, $crawler->filter('[data-audit-input][data-params*="metaTitle"]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-audit-input][data-params*="robotsIndex"]')->count());

        $this->client->request('POST', '/admin/seo/audit/metadata', ['_token' => $token, 'type' => 'product', 'id' => $id, 'locale' => 'en_US', 'field' => 'metaTitle', 'value' => ' A much better title for this product page ']);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/admin/seo/audit/metadata', ['_token' => $token, 'type' => 'product', 'id' => $id, 'locale' => 'en_US', 'field' => 'robotsIndex', 'value' => '1']);
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(SeoMetadata::class)->findOneBy(['subjectType' => 'product', 'subjectId' => $id, 'locale' => 'en_US']);
        self::assertInstanceOf(SeoMetadata::class, $stored);
        self::assertSame('A much better title for this product page', $stored->getMetaTitle());
        self::assertTrue($stored->isRobotsIndex());
    }

    public function testSavingAMetadataFieldNeedsAValidTokenAndKnownField(): void
    {
        $this->logInAsAdministrator();
        $crawler = $this->client->request('GET', '/admin/seo/audit');
        $token = $crawler->filter('[data-audit-image-alt-root]')->attr('data-token');

        $this->client->request('POST', '/admin/seo/audit/metadata', ['_token' => 'invalid', 'type' => 'product', 'id' => 1, 'locale' => 'en_US', 'field' => 'metaTitle', 'value' => 'x']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/seo/audit/metadata', ['_token' => $token, 'type' => 'product', 'id' => 1, 'locale' => 'en_US', 'field' => 'canonicalUrl', 'value' => 'x']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAuditRequiresAnAdministrator(): void
    {
        $this->client->request('GET', '/admin/seo/audit');

        self::assertResponseRedirects();
    }
}
