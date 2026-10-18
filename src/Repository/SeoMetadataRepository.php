<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Repository;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SeoMetadata> */
class SeoMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoMetadata::class);
    }

    public function findOneForSubject(string $type, int $id, string $locale): ?SeoMetadata
    {
        return $this->findOneBy(['subjectType' => $type, 'subjectId' => $id, 'locale' => $locale]);
    }

    /** @return list<SeoMetadata> */
    public function findAllForSubject(string $type, int $id): array
    {
        return array_values($this->findBy(['subjectType' => $type, 'subjectId' => $id]));
    }

    /**
     * Subjects excluded from indexing, as a set keyed by "id|locale".
     *
     * @return array<string, true>
     */
    public function findNoIndexKeys(string $type): array
    {
        /** @var list<array{subjectId: int, locale: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.subjectId', 'm.locale')
            ->where('m.subjectType = :type')
            ->andWhere('m.robotsIndex = false')
            ->setParameter('type', $type)
            ->getQuery()
            ->getArrayResult();

        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['subjectId'] . '|' . $row['locale']] = true;
        }

        return $keys;
    }
}
