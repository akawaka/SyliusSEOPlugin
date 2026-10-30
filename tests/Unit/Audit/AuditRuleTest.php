<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\Audit;

use Akawaka\SyliusSEOPlugin\Enum\AuditField;
use Akawaka\SyliusSEOPlugin\Enum\AuditRule;
use Akawaka\SyliusSEOPlugin\Enum\AuditSeverity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuditRuleTest extends TestCase
{
    /** @return iterable<string, array{int, ?AuditRule}> */
    public static function titleLengths(): iterable
    {
        yield 'missing' => [0, AuditRule::TitleMissing];
        yield 'too short' => [AuditRule::TITLE_MIN - 1, AuditRule::TitleTooShort];
        yield 'lower bound is fine' => [AuditRule::TITLE_MIN, null];
        yield 'upper bound is fine' => [AuditRule::TITLE_MAX, null];
        yield 'too long' => [AuditRule::TITLE_MAX + 1, AuditRule::TitleTooLong];
    }

    #[DataProvider('titleLengths')]
    public function testTitleLength(int $length, ?AuditRule $expected): void
    {
        self::assertSame($expected, AuditRule::forTitleLength($length));
    }

    /** @return iterable<string, array{int, ?AuditRule}> */
    public static function descriptionLengths(): iterable
    {
        yield 'missing' => [0, AuditRule::DescriptionMissing];
        yield 'too short' => [AuditRule::DESCRIPTION_MIN - 1, AuditRule::DescriptionTooShort];
        yield 'lower bound is fine' => [AuditRule::DESCRIPTION_MIN, null];
        yield 'upper bound is fine' => [AuditRule::DESCRIPTION_MAX, null];
        yield 'too long' => [AuditRule::DESCRIPTION_MAX + 1, AuditRule::DescriptionTooLong];
    }

    #[DataProvider('descriptionLengths')]
    public function testDescriptionLength(int $length, ?AuditRule $expected): void
    {
        self::assertSame($expected, AuditRule::forDescriptionLength($length));
    }

    public function testMissingTextsAreErrorsAndEveryRuleHasASeverityAndLabel(): void
    {
        self::assertSame(AuditSeverity::Error, AuditRule::TitleMissing->severity());
        self::assertSame(AuditSeverity::Error, AuditRule::DescriptionMissing->severity());
        self::assertSame(AuditSeverity::Info, AuditRule::NoIndex->severity());

        foreach (AuditRule::cases() as $rule) {
            self::assertSame('akawaka_seo.audit.rule.' . $rule->value, $rule->label());
        }
    }

    public function testEachRuleMapsToTheFieldThatFixesIt(): void
    {
        self::assertSame(AuditField::MetaTitle, AuditRule::TitleTooShort->field());
        self::assertSame(AuditField::MetaTitle, AuditRule::TitleDuplicate->field());
        self::assertSame(AuditField::MetaDescription, AuditRule::DescriptionMissing->field());
        self::assertSame(AuditField::RobotsIndex, AuditRule::NoIndex->field());
        self::assertNull(AuditRule::ProductWithoutImage->field());
        self::assertNull(AuditRule::ImageWithoutAlt->field());
    }
}
