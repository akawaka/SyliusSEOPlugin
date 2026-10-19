<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;

interface SeoMetadataInterface extends ResourceInterface
{
    public function getSubjectType(): string;

    public function setSubjectType(string $subjectType): void;

    public function getSubjectId(): int;

    public function setSubjectId(int $subjectId): void;

    public function getLocale(): string;

    public function setLocale(string $locale): void;

    public function getMetaTitle(): ?string;

    public function setMetaTitle(?string $metaTitle): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;

    public function getCanonicalUrl(): ?string;

    public function setCanonicalUrl(?string $canonicalUrl): void;

    public function isRobotsIndex(): bool;

    public function setRobotsIndex(bool $robotsIndex): void;

    public function isRobotsFollow(): bool;

    public function setRobotsFollow(bool $robotsFollow): void;

    public function getOgTitle(): ?string;

    public function setOgTitle(?string $ogTitle): void;

    public function getOgDescription(): ?string;

    public function setOgDescription(?string $ogDescription): void;

    public function getOgImage(): ?string;

    public function setOgImage(?string $ogImage): void;
}
