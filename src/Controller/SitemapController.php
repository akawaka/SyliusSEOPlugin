<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Controller;

use Akawaka\SyliusSEOPlugin\Sitemap\SitemapGenerator;
use Symfony\Component\HttpFoundation\Response;

final class SitemapController
{
    public function __construct(private readonly SitemapGenerator $generator)
    {
    }

    public function indexAction(): Response
    {
        return $this->xml($this->generator->index());
    }

    public function sectionAction(string $type, int $page): Response
    {
        $xml = $this->generator->section($type, $page);

        return null === $xml ? new Response('Sitemap not found.', Response::HTTP_NOT_FOUND) : $this->xml($xml);
    }

    private function xml(string $content): Response
    {
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);
        // One URL path, one document per channel host: shared caches must key on the host.
        $response->setVary(['Host'], false);

        return $response;
    }
}
