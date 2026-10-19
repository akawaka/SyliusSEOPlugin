<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\Resolver;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadata;
use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Akawaka\SyliusSEOPlugin\Resolver\AlternateUrlGenerator;
use Akawaka\SyliusSEOPlugin\Resolver\PlaceholderRenderer;
use Akawaka\SyliusSEOPlugin\Resolver\SeoMetadataResolver;
use Akawaka\SyliusSEOPlugin\Resolver\SubjectTypeResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class SeoMetadataResolverTest extends TestCase
{
    private const SUBJECTS = [
        'product' => ['class' => ProductInterface::class, 'title_template' => '{name} | {channel}', 'description_template' => '{description}'],
    ];

    private function resolver(?SeoMetadata $stored, int $maxLength = 160): SeoMetadataResolver
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getName')->willReturn('Shop');
        $channel->method('getLocales')->willReturn(new ArrayCollection());

        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $localeContext = $this->createStub(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('en_US');

        $repository = $this->createStub(SeoMetadataRepository::class);
        $repository->method('findOneForSubject')->willReturn($stored);

        $requests = new RequestStack();
        $requests->push(Request::create('https://shop.test/en_US/products/mug?page=2'));

        return new SeoMetadataResolver(
            new SubjectTypeResolver(self::SUBJECTS),
            $repository,
            new PlaceholderRenderer(),
            new AlternateUrlGenerator($this->createStub(RouterInterface::class)),
            $channelContext,
            $localeContext,
            $requests,
            $maxLength,
        );
    }

    private function product(string $description = '<p>A <b>great</b> mug</p>'): ProductInterface
    {
        $product = $this->createStub(ProductInterface::class);
        $product->method('getId')->willReturn(7);
        $product->method('getName')->willReturn('Mug');
        $product->method('getDescription')->willReturn($description);

        return $product;
    }

    public function testTemplatesAreUsedWhenNothingIsStored(): void
    {
        $metadata = $this->resolver(null)->resolve($this->product());

        self::assertNotNull($metadata);
        self::assertSame('Mug | Shop', $metadata->title);
        self::assertSame('A great mug', $metadata->description);
        self::assertSame('index,follow', $metadata->robots);
        self::assertSame('https://shop.test/en_US/products/mug', $metadata->canonicalUrl);
        self::assertSame('Mug | Shop', $metadata->ogTitle);
        self::assertSame('product', $metadata->ogType);
        self::assertNull($metadata->ogImage);
    }

    public function testStoredValuesOverrideTheTemplatesAndBlankOnesFallBack(): void
    {
        $stored = new SeoMetadata();
        $stored->setSubjectType('product');
        $stored->setSubjectId(7);
        $stored->setLocale('en_US');
        $stored->setMetaTitle('  Custom title ');
        $stored->setMetaDescription('   ');
        $stored->setCanonicalUrl('https://example.com/canonical');
        $stored->setRobotsIndex(false);
        $stored->setRobotsFollow(false);
        $stored->setOgImage('https://example.com/og.png');

        $metadata = $this->resolver($stored)->resolve($this->product());

        self::assertNotNull($metadata);
        self::assertSame('Custom title', $metadata->title);
        self::assertSame('A great mug', $metadata->description);
        self::assertSame('https://example.com/canonical', $metadata->canonicalUrl);
        self::assertSame('noindex,nofollow', $metadata->robots);
        self::assertSame('Custom title', $metadata->ogTitle);
        self::assertSame('https://example.com/og.png', $metadata->ogImage);
    }

    public function testLongDescriptionsAreCutOnAWordBoundary(): void
    {
        $metadata = $this->resolver(null, 20)->resolve($this->product('The quick brown fox jumps over the lazy dog'));

        self::assertNotNull($metadata);
        self::assertSame('The quick brown…', $metadata->description);
        self::assertLessThanOrEqual(20, mb_strlen($metadata->description));
    }

    public function testUnknownOrUnsavedSubjectsAreIgnored(): void
    {
        self::assertNull($this->resolver(null)->resolve(new \stdClass()));

        $unsaved = $this->createStub(ProductInterface::class);
        $unsaved->method('getId')->willReturn(null);
        self::assertNull($this->resolver(null)->resolve($unsaved));
    }
}
