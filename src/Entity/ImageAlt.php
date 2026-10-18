<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Akawaka\SyliusSEOPlugin\Repository\ImageAltRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Alternative text of an image in one locale. Keyed by the stored file path (unique per upload),
 * so it applies to product and taxon images alike.
 */
#[ORM\Entity(repositoryClass: ImageAltRepository::class)]
#[ORM\Table(name: 'akawaka_seo_image_alt')]
#[ORM\UniqueConstraint(name: 'akawaka_seo_image_alt_path_locale', columns: ['image_path', 'locale'])]
class ImageAlt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'image_path', type: 'string', length: 255)]
    private string $imagePath;

    #[ORM\Column(type: 'string', length: 10)]
    private string $locale;

    #[ORM\Column(type: 'string', length: 255)]
    private string $alt;

    public function __construct(string $imagePath, string $locale, string $alt)
    {
        $this->imagePath = $imagePath;
        $this->locale = $locale;
        $this->alt = $alt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): void
    {
        $this->alt = $alt;
    }
}
