<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Akawaka\SyliusSEOPlugin\Enum\RedirectStatus;
use Sylius\Resource\Model\ResourceInterface;

interface RedirectInterface extends ResourceInterface
{
    public function getSourcePath(): ?string;

    public function setSourcePath(?string $sourcePath): void;

    public function getTargetUrl(): ?string;

    public function setTargetUrl(?string $targetUrl): void;

    public function getStatus(): RedirectStatus;

    public function setStatus(RedirectStatus $status): void;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    public function isAutomatic(): bool;

    public function setAutomatic(bool $automatic): void;

    public function getHits(): int;

    public function incrementHits(): void;

    public function getCreatedAt(): \DateTimeInterface;
}
