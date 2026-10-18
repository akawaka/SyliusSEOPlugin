<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Enum;

enum AuditSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return 'akawaka_seo.audit.severity.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Error => 'bg-danger',
            self::Warning => 'bg-warning',
            self::Info => 'bg-info',
        };
    }
}
