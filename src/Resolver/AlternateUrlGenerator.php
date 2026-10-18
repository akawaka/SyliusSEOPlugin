<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Resolver;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/** Builds hreflang alternates: one absolute URL per locale enabled in the channel. */
final class AlternateUrlGenerator
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    /** @return array<string, string> locale code => URL */
    public function generate(object $subject, ChannelInterface $channel): array
    {
        $alternates = [];

        foreach ($channel->getLocales() as $locale) {
            $code = $locale->getCode();
            $url = $this->urlFor($subject, (string) $code);
            if (null !== $url) {
                $alternates[str_replace('_', '-', (string) $code)] = $url;
            }
        }

        return $alternates;
    }

    public function urlFor(object $subject, string $locale): ?string
    {
        try {
            if ($subject instanceof ProductInterface) {
                $slug = $subject->getTranslation($locale)->getSlug();

                return $slug ? $this->router->generate('sylius_shop_product_show', ['slug' => $slug, '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL) : null;
            }

            if ($subject instanceof TaxonInterface) {
                $slug = $subject->getTranslation($locale)->getSlug();

                return $slug ? $this->router->generate('sylius_shop_product_index', ['slug' => $slug, '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL) : null;
            }
        } catch (ExceptionInterface) {
            return null;
        }

        return null;
    }
}
