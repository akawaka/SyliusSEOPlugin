<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit;

use Akawaka\SyliusSEOPlugin\Resolver\PlaceholderRenderer;
use PHPUnit\Framework\TestCase;

final class PlaceholderRendererTest extends TestCase
{
    public function testReplacesSimpleAndNestedPlaceholders(): void
    {
        $subject = new class() {
            public string $code = 'MUG-1';
        };

        $result = (new PlaceholderRenderer())->render('{name} ({subject.code}) | {channel}', [
            'name' => 'Mug',
            'channel' => 'Fashion Web Store',
            'subject' => $subject,
        ]);

        self::assertSame('Mug (MUG-1) | Fashion Web Store', $result);
    }

    public function testUnknownPlaceholdersBecomeEmptyAndWhitespaceIsCollapsed(): void
    {
        self::assertSame('Mug |', (new PlaceholderRenderer())->render('{name} | {missing}', ['name' => 'Mug']));
        self::assertSame('Mug', (new PlaceholderRenderer())->render('{name}{subject.nope}', ['name' => 'Mug', 'subject' => new \stdClass()]));
    }
}
