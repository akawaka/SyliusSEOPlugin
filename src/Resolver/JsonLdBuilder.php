<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Resolver;

use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Akawaka\SyliusSEOPlugin\Model\ResolvedSeoMetadata;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Model\TranslatableInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/** Builds the schema.org JSON-LD graph (Organization, BreadcrumbList, Product / Offer / AggregateRating). */
final class JsonLdBuilder
{
    public function __construct(
        private readonly ProductVariantPricesCalculatorInterface $priceCalculator,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly RouterInterface $router,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function build(object $subject, ?ResolvedSeoMetadata $seo, ?string $image): array
    {
        $channel = $this->channelContext->getChannel();
        \assert($channel instanceof ChannelInterface);

        $nodes = [$this->organization($channel)];

        if (null === $seo) {
            return $subject instanceof TranslatableInterface ? [...$nodes, ...$this->cmsPageNodes($subject)] : $nodes;
        }

        $taxon = match (true) {
            $subject instanceof ProductInterface => $subject->getMainTaxon(),
            $subject instanceof TaxonInterface => $subject,
            default => null,
        };
        $breadcrumb = $this->breadcrumb($taxon, $subject instanceof ProductInterface ? [(string) $subject->getName(), $seo->canonicalUrl] : null);
        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        if ($subject instanceof ProductInterface) {
            $nodes[] = $this->product($subject, $seo, $channel, $image);
        }

        return $nodes;
    }

    /**
     * A CMS page prints its own meta tags: only the structured data is added here.
     *
     * @return list<array<string, mixed>>
     */
    private function cmsPageNodes(TranslatableInterface $page): array
    {
        $locale = $this->localeContext->getLocaleCode();
        foreach ($page->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale && method_exists($translation, 'getSlug') && method_exists($translation, 'getTitle')) {
                $url = $this->url(CmsPages::SHOW_ROUTE, ['slug' => (string) $translation->getSlug()]);
                $breadcrumb = $this->breadcrumb(null, [(string) $translation->getTitle(), (string) $url]);

                return null === $breadcrumb ? [] : [$breadcrumb];
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function organization(ChannelInterface $channel): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $channel->getName(),
            'url' => $this->url('sylius_shop_homepage'),
        ]);
    }

    /**
     * @param array{0: string, 1: string}|null $leaf last item (the product itself)
     *
     * @return array<string, mixed>|null
     */
    private function breadcrumb(?TaxonInterface $taxon, ?array $leaf): ?array
    {
        $locale = $this->localeContext->getLocaleCode();
        $items = [['Home', $this->url('sylius_shop_homepage')]];

        if (null !== $taxon) {
            $path = [...array_reverse($taxon->getAncestors()->toArray()), $taxon];
            foreach ($path as $step) {
                $slug = $step->getTranslation($locale)->getSlug();
                if (null === $step->getParent() || null === $slug) {
                    continue;
                }
                $items[] = [(string) $step->getName(), $this->url('sylius_shop_product_index', ['slug' => $slug])];
            }
        }

        if (null !== $leaf) {
            $items[] = $leaf;
        }

        if (count($items) < 2) {
            return null;
        }

        $elements = [];
        foreach (array_values($items) as $position => [$name, $url]) {
            $elements[] = array_filter(['@type' => 'ListItem', 'position' => $position + 1, 'name' => $name, 'item' => $url]);
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $elements];
    }

    /** @return array<string, mixed> */
    private function product(ProductInterface $product, ResolvedSeoMetadata $seo, ChannelInterface $channel, ?string $image): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getName(),
            'description' => $seo->description ?: null,
            'sku' => $product->getCode(),
            'image' => $image,
            'url' => $seo->canonicalUrl ?: null,
            'offers' => $this->offers($product, $channel, $seo->canonicalUrl),
        ];

        $rating = $product->getAverageRating();
        $count = $product->getAcceptedReviews()->count();
        if (null !== $rating && $count > 0) {
            $data['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($rating, 2), 'reviewCount' => $count];
        }

        return array_filter($data, static fn (mixed $value): bool => null !== $value);
    }

    /** @return array<string, mixed>|null */
    private function offers(ProductInterface $product, ChannelInterface $channel, string $url): ?array
    {
        $currency = $channel->getBaseCurrency()?->getCode();
        $offers = [];

        foreach ($product->getEnabledVariants() as $variant) {
            \assert($variant instanceof ProductVariantInterface);
            if (null === $variant->getChannelPricingForChannel($channel)) {
                continue;
            }

            $offers[] = [
                'price' => $this->price($this->priceCalculator->calculate($variant, ['channel' => $channel])),
                'inStock' => $this->availabilityChecker->isStockAvailable($variant),
            ];
        }

        if ([] === $offers || null === $currency) {
            return null;
        }

        $availability = static fn (bool $inStock): string => 'https://schema.org/' . ($inStock ? 'InStock' : 'OutOfStock');

        if (1 === count($offers)) {
            return array_filter([
                '@type' => 'Offer',
                'price' => $offers[0]['price'],
                'priceCurrency' => $currency,
                'availability' => $availability($offers[0]['inStock']),
                'url' => $url ?: null,
            ]);
        }

        $prices = array_column($offers, 'price');

        return array_filter([
            '@type' => 'AggregateOffer',
            'lowPrice' => min($prices),
            'highPrice' => max($prices),
            'offerCount' => count($offers),
            'priceCurrency' => $currency,
            'availability' => $availability(in_array(true, array_column($offers, 'inStock'), true)),
            'url' => $url ?: null,
        ]);
    }

    private function price(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }

    /** @param array<string, string> $parameters */
    private function url(string $route, array $parameters = []): ?string
    {
        try {
            return $this->router->generate($route, $parameters + ['_locale' => $this->localeContext->getLocaleCode()], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
