<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;

interface RobotsTxtInterface extends ResourceInterface
{
    public function getChannelCode(): string;

    public function setChannelCode(string $channelCode): void;

    public function getContent(): string;

    public function setContent(string $content): void;
}
