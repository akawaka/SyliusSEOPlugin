<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Controller;

use Akawaka\SyliusSEOPlugin\Robots\RobotsTxtManager;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RobotsTxtController
{
    public function __construct(
        private readonly RobotsTxtManager $robotsTxt,
        private readonly ChannelContextInterface $channelContext,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function __invoke(): Response
    {
        $content = $this->robotsTxt->render(
            $this->channelContext->getChannel()->getCode(),
            $this->router->generate('akawaka_seo_sitemap_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);
        // One URL path, one document per channel host: shared caches must key on the host.
        $response->setVary(['Host'], false);

        return $response;
    }
}
