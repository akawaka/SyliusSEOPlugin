<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Enum;

/** Stored SEO override that can be fixed from the audit page. */
enum AuditField: string
{
    case MetaTitle = 'metaTitle';
    case MetaDescription = 'metaDescription';
    case RobotsIndex = 'robotsIndex';

    public function label(): string
    {
        return 'akawaka_seo.audit.field.' . $this->value;
    }
}
