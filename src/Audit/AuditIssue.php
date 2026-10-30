<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Audit;

use Akawaka\SyliusSEOPlugin\Enum\AuditField;
use Akawaka\SyliusSEOPlugin\Enum\AuditRule;

final class AuditIssue
{
    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $subjectLabel,
        public readonly AuditRule $rule,
        public readonly ?int $imageId = null,
        public readonly ?string $imagePath = null,
        public readonly ?string $imageType = null,
        public readonly int $imagePosition = 1,
        public readonly ?AuditField $field = null,
        public readonly ?string $value = null,
    ) {
    }
}
