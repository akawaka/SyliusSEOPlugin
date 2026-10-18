<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\EventListener;

use Akawaka\SyliusSEOPlugin\Robots\RobotsTxtManager;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class ChannelSavedListener
{
    public function __construct(private readonly RobotsTxtManager $robotsTxt)
    {
    }

    public function __invoke(GenericEvent $event): void
    {
        $channel = $event->getSubject();

        if ($channel instanceof ChannelInterface) {
            $this->robotsTxt->persist($channel);
        }
    }
}
