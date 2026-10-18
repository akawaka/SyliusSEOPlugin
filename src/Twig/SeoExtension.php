<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Twig;

use Akawaka\SyliusSEOPlugin\Model\ResolvedSeoMetadata;
use Akawaka\SyliusSEOPlugin\Resolver\AlternateUrlGenerator;
use Akawaka\SyliusSEOPlugin\Resolver\CurrentSubjectProvider;
use Akawaka\SyliusSEOPlugin\Resolver\JsonLdBuilder;
use Akawaka\SyliusSEOPlugin\Resolver\SeoMetadataResolver;
use Sylius\Resource\Model\TranslatableInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SeoExtension extends AbstractExtension
{
    public const TITLE_ATTRIBUTE = '_akawaka_seo_title';

    public function __construct(
        private readonly SeoMetadataResolver $resolver,
        private readonly CurrentSubjectProvider $subjectProvider,
        private readonly JsonLdBuilder $jsonLdBuilder,
        private readonly AlternateUrlGenerator $urls,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('akawaka_seo_metadata', $this->metadata(...)),
            new TwigFunction('akawaka_seo_subject', $this->subjectProvider->get(...)),
            new TwigFunction('akawaka_seo_url', $this->storefrontUrl(...)),
            new TwigFunction('akawaka_seo_json_ld', $this->jsonLd(...), ['is_safe' => ['html']]),
        ];
    }

    /** Resolves metadata for the page subject and remembers the title for the response listener. */
    public function metadata(?object $subject = null): ?ResolvedSeoMetadata
    {
        $subject ??= $this->subjectProvider->get();
        if (null === $subject) {
            return null;
        }

        $metadata = $this->resolver->resolve($subject);
        if (null !== $metadata && '' !== $metadata->title) {
            $this->requestStack->getMainRequest()?->attributes->set(self::TITLE_ATTRIBUTE, $metadata->title);
        }

        return $metadata;
    }

    /** Returns the JSON-LD nodes as a string that is safe to embed in a script tag. */
    public function jsonLd(object $subject, ?ResolvedSeoMetadata $seo = null, ?string $image = null): string
    {
        $nodes = $this->jsonLdBuilder->build($subject, $seo, $image);

        return (string) json_encode($nodes, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /** Storefront URL of a subject in a locale, or null while it has no slug there (e.g. on creation). */
    public function storefrontUrl(?object $subject, string $locale): ?string
    {
        if (!$subject instanceof TranslatableInterface) {
            return null;
        }

        // getTranslation() would add an empty translation to the entity being edited.
        foreach ($subject->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $this->urls->urlFor($subject, $locale);
            }
        }

        return null;
    }
}
