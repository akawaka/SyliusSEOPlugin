<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\EventListener;

use Akawaka\SyliusSEOPlugin\Twig\SeoExtension;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * The Sylius shop layout renders <title> as a Twig block, which a twig hook cannot override.
 * Once the page is rendered, swap it for the resolved SEO title.
 */
final class TitleReplacementListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $title = $event->getRequest()->attributes->get(SeoExtension::TITLE_ATTRIBUTE);
        $response = $event->getResponse();

        if (!$event->isMainRequest() || !is_string($title) || !str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (false === $content) {
            return;
        }

        $replaced = preg_replace(
            '#<title>.*?</title>#is',
            '<title>' . strtr(htmlspecialchars($title, \ENT_QUOTES | \ENT_SUBSTITUTE), ['\\' => '\\\\', '$' => '\\$']) . '</title>',
            $content,
            1,
        );

        if (null !== $replaced) {
            $response->setContent($replaced);
        }
    }
}
