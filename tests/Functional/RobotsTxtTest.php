<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Functional;

use Akawaka\SyliusSEOPlugin\Entity\RobotsTxt;

final class RobotsTxtTest extends FunctionalTestCase
{
    public function testDefaultContentReferencesTheSitemap(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/plain', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('User-agent: *', $content);
        self::assertStringContainsString('/sitemap.xml', $content);
    }

    public function testChannelContentIsServedAndEditableFromTheAdmin(): void
    {
        $this->logInAsAdministrator();
        $channel = static::getContainer()->get('sylius.repository.channel')->findOneBy([]);
        $crawler = $this->client->request('GET', '/admin/channels/' . $channel->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="sylius_admin_channel"]')->form();
        $form['sylius_admin_channel[akawaka_robots_txt]'] = "User-agent: *\nDisallow: /private/";
        $this->client->submit($form);

        $this->entityManager()->clear();
        self::assertCount(1, $this->entityManager()->getRepository(RobotsTxt::class)->findAll());

        $this->client->request('GET', '/robots.txt');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Disallow: /private/', $content);
        self::assertStringNotContainsString('Disallow: /admin/', $content);
        self::assertStringContainsString('Sitemap: http', $content);
    }
}
