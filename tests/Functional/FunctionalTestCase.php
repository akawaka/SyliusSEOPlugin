<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\ImageAlt;
use Akawaka\SyliusSEOPlugin\Entity\RobotsTxt;
use Akawaka\SyliusSEOPlugin\Entity\SeoMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Boots the test application; requires the test database to be created, migrated and loaded with fixtures. */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();

        parent::tearDown();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function firstProduct(): ProductInterface
    {
        $product = static::getContainer()->get('sylius.repository.product')->findOneBy([]);
        \assert($product instanceof ProductInterface);

        return $product;
    }

    protected function productUrl(ProductInterface $product, string $locale = 'en_US'): string
    {
        return '/' . $locale . '/products/' . $product->getTranslation($locale)->getSlug();
    }

    protected function createSeoMetadata(ProductInterface $product, string $locale = 'en_US'): SeoMetadata
    {
        $metadata = new SeoMetadata('product', (int) $product->getId(), $locale);
        $this->entityManager()->persist($metadata);

        return $metadata;
    }

    protected function logInAsAdministrator(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $this->client->submit($crawler->selectButton('Login')->form(['_username' => 'sylius', '_password' => 'sylius']));
        $this->client->followRedirect();
    }

    private function purge(): void
    {
        $em = $this->entityManager();
        $em->clear();
        $em->createQuery('DELETE FROM ' . SeoMetadata::class)->execute();
        $em->createQuery('DELETE FROM ' . RobotsTxt::class)->execute();
        $em->createQuery('DELETE FROM ' . ImageAlt::class)->execute();
    }
}
