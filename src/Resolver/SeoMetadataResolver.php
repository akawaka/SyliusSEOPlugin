<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Resolver;

use Akawaka\SyliusSEOPlugin\Model\ResolvedSeoMetadata;
use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Merges stored SeoMetadata with the configured templates to produce the final head values. */
final class SeoMetadataResolver
{
    public function __construct(
        private readonly SubjectTypeResolver $subjectTypes,
        private readonly SeoMetadataRepository $repository,
        private readonly PlaceholderRenderer $renderer,
        private readonly AlternateUrlGenerator $alternates,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly RequestStack $requestStack,
        private readonly int $descriptionMaxLength,
    ) {
    }

    public function resolve(object $subject): ?ResolvedSeoMetadata
    {
        $channel = $this->channelContext->getChannel();
        \assert($channel instanceof ChannelInterface);

        return $this->resolveFor($subject, $channel, $this->localeContext->getLocaleCode());
    }

    public function resolveFor(object $subject, ChannelInterface $channel, string $locale): ?ResolvedSeoMetadata
    {
        $type = $this->subjectTypes->resolveType($subject);
        if (null === $type || !method_exists($subject, 'getId') || null === $subject->getId()) {
            return null;
        }

        $stored = $this->repository->findOneForSubject($type, (int) $subject->getId(), $locale);
        $templates = $this->subjectTypes->templatesFor($type);

        $context = [
            'subject' => $subject,
            'name' => method_exists($subject, 'getName') ? $subject->getName() : '',
            'description' => $this->plainText(method_exists($subject, 'getDescription') ? (string) $subject->getDescription() : ''),
            'channel' => $channel->getName(),
        ];

        $title = $this->firstFilled($stored?->getMetaTitle()) ?? $this->renderer->render($templates['title_template'], $context);
        $description = $this->firstFilled($stored?->getMetaDescription())
            ?? ($templates['description_template'] ? $this->renderer->render($templates['description_template'], $context) : '');
        $description = $this->truncate($description);

        $robots = sprintf(
            '%s,%s',
            ($stored?->isRobotsIndex() ?? true) ? 'index' : 'noindex',
            ($stored?->isRobotsFollow() ?? true) ? 'follow' : 'nofollow',
        );

        return new ResolvedSeoMetadata(
            title: $title,
            description: $description,
            canonicalUrl: $this->firstFilled($stored?->getCanonicalUrl()) ?? $this->currentUrl(),
            robots: $robots,
            ogTitle: $this->firstFilled($stored?->getOgTitle()) ?? $title,
            ogDescription: $this->truncate($this->firstFilled($stored?->getOgDescription()) ?? $description),
            ogImage: $this->firstFilled($stored?->getOgImage()),
            ogType: $subject instanceof ProductInterface ? 'product' : 'website',
            ogLocale: $locale,
            siteName: (string) $channel->getName(),
            alternates: $this->alternates->generate($subject, $channel),
        );
    }

    private function firstFilled(?string $value): ?string
    {
        return null !== $value && '' !== trim($value) ? trim($value) : null;
    }

    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))) ?? '');
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= $this->descriptionMaxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $this->descriptionMaxLength - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(false !== $lastSpace && $lastSpace > 0 ? mb_substr($cut, 0, $lastSpace) : $cut, ' ,.;:-') . '…';
    }

    private function currentUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return null === $request ? '' : $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
    }
}
