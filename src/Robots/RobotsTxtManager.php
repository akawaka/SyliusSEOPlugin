<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Robots;

use Akawaka\SyliusSEOPlugin\Entity\RobotsTxt;
use Akawaka\SyliusSEOPlugin\Repository\RobotsTxtRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/** Reads and writes the robots.txt of a channel. */
final class RobotsTxtManager
{
    /** @var array<int, string> channel object id => submitted content */
    private array $pending = [];

    public function __construct(
        private readonly RobotsTxtRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** The stored content, or null when the channel uses the default one. */
    public function stored(?string $channelCode): ?string
    {
        return null === $channelCode ? null : $this->repository->findOneForChannel($channelCode)?->getContent();
    }

    public function defaultContent(): string
    {
        return "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /*/cart\nDisallow: /*/checkout\nDisallow: /*/account\n";
    }

    /** Content to serve: the stored one, or the default, always completed by the sitemap location. */
    public function render(?string $channelCode, string $sitemapUrl): string
    {
        $content = rtrim($this->stored($channelCode) ?? $this->defaultContent());

        if (!preg_match('/^\s*sitemap\s*:/im', $content)) {
            $content .= "\n\nSitemap: " . $sitemapUrl;
        }

        return $content . "\n";
    }

    public function stash(ChannelInterface $channel, ?string $content): void
    {
        if (null !== $content) {
            $this->pending[spl_object_id($channel)] = $content;
        }
    }

    public function persist(ChannelInterface $channel): void
    {
        $content = $this->pending[spl_object_id($channel)] ?? null;
        unset($this->pending[spl_object_id($channel)]);
        $code = $channel->getCode();

        if (null === $content || null === $code) {
            return;
        }

        $robots = $this->repository->findOneForChannel($code);

        if ('' === trim($content)) {
            null !== $robots && $this->entityManager->remove($robots);
        } elseif (null === $robots) {
            $this->entityManager->persist(new RobotsTxt($code, $content));
        } else {
            $robots->setContent($content);
        }

        $this->entityManager->flush();
    }
}
