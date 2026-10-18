<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

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
        $crawler = $this->client->request('GET', '/admin/seo/audit');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-audit-issue="title_too_short"]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-audit-issue="no_index"]')->count());
        self::assertStringContainsString((string) $product->getName(), $crawler->filter('table')->text());
    }

    public function testAuditRequiresAnAdministrator(): void
    {
        $this->client->request('GET', '/admin/seo/audit');

        self::assertResponseRedirects();
    }
}
