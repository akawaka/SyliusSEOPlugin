<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\EventListener;

use Akawaka\SyliusSEOPlugin\Repository\ImageAltRepository;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Sylius templates print the product name as alt text, in a handful of places that themes override freely.
 * Rather than copying those templates, the stored alt texts are applied to the rendered storefront images.
 */
final class ImageAltListener
{
    private const IMG = '#<img\b[^>]*>#i';

    private const CACHE_PATH = '#/media/cache/(?:resolve/)?[^/"\']+/([^"\'?\s]+)#';

    public function __construct(private readonly ImageAltRepository $repository)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $route = $event->getRequest()->attributes->getString('_route');
        if (!$event->isMainRequest() || str_starts_with($route, 'sylius_admin') || str_starts_with($route, 'akawaka_seo_admin') || !str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (false === $content || !preg_match_all(self::IMG, $content, $matches)) {
            return;
        }

        $paths = [];
        foreach ($matches[0] as $tag) {
            if (null !== ($path = $this->path($tag))) {
                $paths[$path] = true;
            }
        }

        $alts = $this->repository->findAlts(array_keys($paths), $event->getRequest()->getLocale());
        if ([] === $alts) {
            return;
        }

        $replaced = preg_replace_callback(self::IMG, function (array $m) use ($alts): string {
            $path = $this->path($m[0]);
            if (null === $path || !isset($alts[$path])) {
                return $m[0];
            }

            $alt = htmlspecialchars($alts[$path], \ENT_QUOTES | \ENT_SUBSTITUTE);
            $withoutAlt = preg_replace('/\s+alt\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $m[0]) ?? $m[0];

            return preg_replace('/<img\b/i', '<img alt="' . strtr($alt, ['\\' => '\\\\', '$' => '\\$']) . '"', $withoutAlt, 1) ?? $m[0];
        }, $content);

        if (null !== $replaced) {
            $response->setContent($replaced);
        }
    }

    private function path(string $tag): ?string
    {
        return preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $src) && preg_match(self::CACHE_PATH, $src[1], $m) ? urldecode($m[1]) : null;
    }
}
