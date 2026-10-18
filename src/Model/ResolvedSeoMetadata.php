<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Model;

final class ResolvedSeoMetadata
{
    /** @param array<string, string> $alternates hreflang code => absolute URL */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonicalUrl,
        public readonly string $robots,
        public readonly string $ogTitle,
        public readonly string $ogDescription,
        public readonly ?string $ogImage,
        public readonly string $ogType,
        public readonly string $ogLocale,
        public readonly string $siteName,
        public readonly array $alternates = [],
    ) {
    }
}
