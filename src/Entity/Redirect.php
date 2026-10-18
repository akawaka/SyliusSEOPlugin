<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Akawaka\SyliusSEOPlugin\Enum\RedirectStatus;
use Akawaka\SyliusSEOPlugin\Repository\RedirectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'akawaka_seo_redirect')]
#[UniqueEntity(fields: ['sourcePath'], message: 'akawaka_seo.redirect.source_path.unique', groups: ['akawaka_seo'])]
class Redirect implements RedirectInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'source_path', type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank(groups: ['akawaka_seo'])]
    #[Assert\Length(max: 255, groups: ['akawaka_seo'])]
    #[Assert\Regex(pattern: '#^/#', message: 'akawaka_seo.redirect.source_path.slash', groups: ['akawaka_seo'])]
    private ?string $sourcePath = null;

    #[ORM\Column(name: 'target_url', type: 'string', length: 2048)]
    #[Assert\NotBlank(groups: ['akawaka_seo'])]
    #[Assert\Length(max: 2048, groups: ['akawaka_seo'])]
    #[Assert\Regex(pattern: '#^(/|https?://)#', message: 'akawaka_seo.redirect.target_url.format', groups: ['akawaka_seo'])]
    #[Assert\Expression('this.getTargetUrl() !== this.getSourcePath()', message: 'akawaka_seo.redirect.target_url.loop', groups: ['akawaka_seo'])]
    private ?string $targetUrl = null;

    #[ORM\Column(type: 'integer', enumType: RedirectStatus::class)]
    private RedirectStatus $status = RedirectStatus::Permanent;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'boolean')]
    private bool $automatic = false;

    #[ORM\Column(type: 'integer')]
    private int $hits = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(?string $sourcePath): void
    {
        $this->sourcePath = null === $sourcePath ? null : trim($sourcePath);
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = null === $targetUrl ? null : trim($targetUrl);
    }

    public function getStatus(): RedirectStatus
    {
        return $this->status;
    }

    public function setStatus(RedirectStatus $status): void
    {
        $this->status = $status;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isAutomatic(): bool
    {
        return $this->automatic;
    }

    public function setAutomatic(bool $automatic): void
    {
        $this->automatic = $automatic;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function incrementHits(): void
    {
        ++$this->hits;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
