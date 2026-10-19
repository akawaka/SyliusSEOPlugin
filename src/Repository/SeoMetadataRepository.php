<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Repository;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadataInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class SeoMetadataRepository extends EntityRepository
{
    public function findOneForSubject(string $type, int $id, string $locale): ?SeoMetadataInterface
    {
        $metadata = $this->findOneBy(['subjectType' => $type, 'subjectId' => $id, 'locale' => $locale]);

        return $metadata instanceof SeoMetadataInterface ? $metadata : null;
    }

    /** @return list<SeoMetadataInterface> */
    public function findAllForSubject(string $type, int $id): array
    {
        return array_values(array_filter(
            $this->findBy(['subjectType' => $type, 'subjectId' => $id]),
            static fn (object $metadata): bool => $metadata instanceof SeoMetadataInterface,
        ));
    }

    public function deleteForSubject(string $type, int $id): void
    {
        $this->createQueryBuilder('m')
            ->delete()
            ->where('m.subjectType = :type')->andWhere('m.subjectId = :id')
            ->setParameter('type', $type)->setParameter('id', $id)
            ->getQuery()->execute();
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
