<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Enum;

enum RedirectStatus: int
{
    case Permanent = 301;
    case Temporary = 302;

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'akawaka_seo.redirect.status.permanent',
            self::Temporary => 'akawaka_seo.redirect.status.temporary',
        };
    }
}
