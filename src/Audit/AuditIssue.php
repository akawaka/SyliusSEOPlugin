<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Audit;

use Akawaka\SyliusSEOPlugin\Enum\AuditRule;

final class AuditIssue
{
    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $subjectLabel,
        public readonly AuditRule $rule,
    ) {
    }
}
