<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Enum;

enum AuditRule: string
{
    case TitleMissing = 'title_missing';
    case TitleTooShort = 'title_too_short';
    case TitleTooLong = 'title_too_long';
    case TitleDuplicate = 'title_duplicate';
    case DescriptionMissing = 'description_missing';
    case DescriptionTooShort = 'description_too_short';
    case DescriptionTooLong = 'description_too_long';
    case NoIndex = 'no_index';
    case ProductWithoutImage = 'product_without_image';
    case ImageWithoutAlt = 'image_without_alt';

    public function severity(): AuditSeverity
    {
        return match ($this) {
            self::TitleMissing, self::DescriptionMissing => AuditSeverity::Error,
            self::TitleTooShort, self::TitleTooLong, self::TitleDuplicate, self::DescriptionTooShort, self::DescriptionTooLong, self::ProductWithoutImage, self::ImageWithoutAlt => AuditSeverity::Warning,
            self::NoIndex => AuditSeverity::Info,
        };
    }

    public function label(): string
    {
        return 'akawaka_seo.audit.rule.' . $this->value;
    }
}
