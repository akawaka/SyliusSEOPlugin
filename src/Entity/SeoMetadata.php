<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * SEO overrides for one subject (product, taxon, ...) in one locale.
 * Any null field falls back to the configured template.
 */
#[ORM\Entity(repositoryClass: SeoMetadataRepository::class)]
#[ORM\Table(name: 'akawaka_seo_metadata')]
#[ORM\UniqueConstraint(name: 'akawaka_seo_subject_locale', columns: ['subject_type', 'subject_id', 'locale'])]
class SeoMetadata implements SeoMetadataInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'subject_type', type: 'string', length: 50)]
    private string $subjectType;

    #[ORM\Column(name: 'subject_id', type: 'integer')]
    private int $subjectId;

    #[ORM\Column(type: 'string', length: 10)]
    private string $locale;

    #[ORM\Column(name: 'meta_title', type: 'string', length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(name: 'meta_description', type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(name: 'canonical_url', type: 'string', length: 2048, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(name: 'robots_index', type: 'boolean')]
    private bool $robotsIndex = true;

    #[ORM\Column(name: 'robots_follow', type: 'boolean')]
    private bool $robotsFollow = true;

    #[ORM\Column(name: 'og_title', type: 'string', length: 255, nullable: true)]
    private ?string $ogTitle = null;

    #[ORM\Column(name: 'og_description', type: 'text', nullable: true)]
    private ?string $ogDescription = null;

    #[ORM\Column(name: 'og_image', type: 'string', length: 2048, nullable: true)]
    private ?string $ogImage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function setSubjectType(string $subjectType): void
    {
        $this->subjectType = $subjectType;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function setSubjectId(int $subjectId): void
    {
        $this->subjectId = $subjectId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): void
    {
        $this->canonicalUrl = $canonicalUrl;
    }

    public function isRobotsIndex(): bool
    {
        return $this->robotsIndex;
    }

    public function setRobotsIndex(bool $robotsIndex): void
    {
        $this->robotsIndex = $robotsIndex;
    }

    public function isRobotsFollow(): bool
    {
        return $this->robotsFollow;
    }

    public function setRobotsFollow(bool $robotsFollow): void
    {
        $this->robotsFollow = $robotsFollow;
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function setOgTitle(?string $ogTitle): void
    {
        $this->ogTitle = $ogTitle;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $ogDescription): void
    {
        $this->ogDescription = $ogDescription;
    }

    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    public function setOgImage(?string $ogImage): void
    {
        $this->ogImage = $ogImage;
    }
}
