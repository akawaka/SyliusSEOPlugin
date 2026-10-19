<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Akawaka\SyliusSEOPlugin\Repository\RobotsTxtRepository;
use Doctrine\ORM\Mapping as ORM;

/** Custom robots.txt content of one channel; no row means the default content is served. */
#[ORM\Entity(repositoryClass: RobotsTxtRepository::class)]
#[ORM\Table(name: 'akawaka_seo_robots_txt')]
class RobotsTxt implements RobotsTxtInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'channel_code', type: 'string', length: 255, unique: true)]
    private string $channelCode;

    #[ORM\Column(type: 'text')]
    private string $content;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $channelCode): void
    {
        $this->channelCode = $channelCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
