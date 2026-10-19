<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class ImageAltRepository extends EntityRepository
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, string> image path => alt
     */
    public function findAlts(array $paths, string $locale): array
    {
        if ([] === $paths) {
            return [];
        }

        /** @var list<array{imagePath: string, alt: string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.imagePath', 'a.alt')
            ->where('a.imagePath IN (:paths)')->andWhere('a.locale = :locale')
            ->setParameter('paths', $paths)->setParameter('locale', $locale)
            ->getQuery()->getArrayResult();

        return array_column($rows, 'alt', 'imagePath');
    }
}
