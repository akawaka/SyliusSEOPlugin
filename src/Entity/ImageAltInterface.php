<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;

interface ImageAltInterface extends ResourceInterface
{
    public function getImagePath(): string;

    public function setImagePath(string $imagePath): void;

    public function getLocale(): string;

    public function setLocale(string $locale): void;

    public function getAlt(): string;

    public function setAlt(string $alt): void;
}
