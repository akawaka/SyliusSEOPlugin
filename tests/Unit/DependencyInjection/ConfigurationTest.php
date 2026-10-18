<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\DependencyInjection;

use Akawaka\SyliusSEOPlugin\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testProductAndTaxonDefaultsAreKeptWhenNothingIsConfigured(): void
    {
        $subjects = Configuration::withDefaultSubjects([]);

        self::assertSame(['product', 'taxon'], array_keys($subjects));
        self::assertSame('{name} | {channel}', $subjects['product']['title_template']);
    }

    public function testOverridingOneOptionKeepsTheOthersAndTheOtherSubject(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['subjects' => ['product' => ['title_template' => '{name}']]]]);
        $subjects = Configuration::withDefaultSubjects($config['subjects']);

        self::assertSame('{name}', $subjects['product']['title_template']);
        self::assertSame('{description}', $subjects['product']['description_template']);
        self::assertSame('Sylius\Component\Core\Model\ProductInterface', $subjects['product']['class']);
        self::assertSame('{name} | {channel}', $subjects['taxon']['title_template']);
    }

    public function testACustomSubjectNeedsAClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Configuration::withDefaultSubjects(['page' => ['title_template' => '{name}']]);
    }

    public function testDescriptionLengthAndSitemapPageSizeHaveDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame(160, $config['description_max_length']);
        self::assertSame(5000, $config['sitemap']['page_size']);
    }
}
