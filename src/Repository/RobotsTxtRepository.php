<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Repository;

use Akawaka\SyliusSEOPlugin\Entity\RobotsTxtInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class RobotsTxtRepository extends EntityRepository
{
    public function findOneForChannel(string $channelCode): ?RobotsTxtInterface
    {
        $robots = $this->findOneBy(['channelCode' => $channelCode]);

        return $robots instanceof RobotsTxtInterface ? $robots : null;
    }
}
