<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Repository;

use Akawaka\SyliusSEOPlugin\Entity\RobotsTxt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RobotsTxt> */
class RobotsTxtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RobotsTxt::class);
    }

    public function findOneForChannel(string $channelCode): ?RobotsTxt
    {
        return $this->findOneBy(['channelCode' => $channelCode]);
    }
}
