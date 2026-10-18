<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\Robots;

use Akawaka\SyliusSEOPlugin\Entity\RobotsTxt;
use Akawaka\SyliusSEOPlugin\Repository\RobotsTxtRepository;
use Akawaka\SyliusSEOPlugin\Robots\RobotsTxtManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;

final class RobotsTxtManagerTest extends TestCase
{
    private function manager(?RobotsTxt $stored, ?EntityManagerInterface $entityManager = null): RobotsTxtManager
    {
        $repository = $this->createStub(RobotsTxtRepository::class);
        $repository->method('findOneForChannel')->willReturn($stored);

        return new RobotsTxtManager($repository, $entityManager ?? $this->createStub(EntityManagerInterface::class));
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB');

        return $channel;
    }

    public function testDefaultContentGetsTheSitemapLine(): void
    {
        $content = $this->manager(null)->render('WEB', 'https://shop.test/sitemap.xml');

        self::assertStringStartsWith("User-agent: *\n", $content);
        self::assertStringEndsWith("\n\nSitemap: https://shop.test/sitemap.xml\n", $content);
    }

    public function testStoredContentIsServedAndAnExistingSitemapLineIsKept(): void
    {
        $stored = new RobotsTxt('WEB', "User-agent: *\nDisallow: /x\nsitemap: https://other.test/map.xml\n");
        $content = $this->manager($stored)->render('WEB', 'https://shop.test/sitemap.xml');

        self::assertStringContainsString('Disallow: /x', $content);
        self::assertSame(1, substr_count(strtolower($content), 'sitemap:'));
    }

    public function testSubmittedContentIsCreatedUpdatedOrRemoved(): void
    {
        $channel = $this->channel();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(static fn (RobotsTxt $r): bool => 'WEB' === $r->getChannelCode() && 'Disallow: /a' === $r->getContent()));
        $entityManager->expects(self::once())->method('flush');
        $manager = $this->manager(null, $entityManager);
        $manager->stash($channel, 'Disallow: /a');
        $manager->persist($channel);

        $existing = new RobotsTxt('WEB', 'old');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $manager = $this->manager($existing, $entityManager);
        $manager->stash($channel, 'new');
        $manager->persist($channel);
        self::assertSame('new', $existing->getContent());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($existing);
        $manager = $this->manager($existing, $entityManager);
        $manager->stash($channel, "  \n");
        $manager->persist($channel);
    }

    public function testNothingIsWrittenWithoutSubmittedContent(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->manager(null, $entityManager)->persist($this->channel());
    }
}
