<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Resolver;

use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Finds the entity displayed by the current storefront request. Twig hooks of the layout do not
 * receive the variables of the page, so the subject is loaded again from the route (identity map hit).
 */
final class CurrentSubjectProvider
{
    private bool $loaded = false;

    private ?object $subject = null;

    /**
     * @param ProductRepositoryInterface<\Sylius\Component\Core\Model\ProductInterface> $productRepository
     * @param TaxonRepositoryInterface<\Sylius\Component\Core\Model\TaxonInterface> $taxonRepository
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly ?CmsPages $cmsPages = null,
    ) {
    }

    public function get(): ?object
    {
        if (!$this->loaded) {
            $this->subject = $this->load();
            $this->loaded = true;
        }

        return $this->subject;
    }

    private function load(): ?object
    {
        $request = $this->requestStack->getMainRequest();
        $slug = $request?->attributes->get('slug');
        if (null === $request || !is_string($slug)) {
            return null;
        }

        $locale = $this->localeContext->getLocaleCode();

        return match ($request->attributes->get('_route')) {
            'sylius_shop_product_show' => $this->productRepository->findOneByChannelAndSlug($this->channel(), $locale, $slug),
            'sylius_shop_product_index' => $this->taxonRepository->findOneBySlug($slug, $locale),
            CmsPages::SHOW_ROUTE => $this->cmsPages?->findOneBySlug($slug, $locale, $this->channel()),
            default => null,
        };
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->channelContext->getChannel();
        \assert($channel instanceof ChannelInterface);

        return $channel;
    }
}
