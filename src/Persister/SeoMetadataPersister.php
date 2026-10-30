<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Persister;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadataInterface;
use Akawaka\SyliusSEOPlugin\Enum\AuditField;
use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Akawaka\SyliusSEOPlugin\Resolver\SubjectTypeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The admin form only collects the data; the rows are written once the subject itself is saved
 * (its id is unknown while a new product or taxon is still being created).
 */
final class SeoMetadataPersister
{
    private const FIELDS = ['metaTitle', 'metaDescription', 'canonicalUrl', 'ogTitle', 'ogDescription', 'ogImage'];

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $pending = [];

    /** @var array<int, array{0: string, 1: int}> */
    private array $removals = [];

    /** @param FactoryInterface<SeoMetadataInterface> $factory */
    public function __construct(
        private readonly SeoMetadataRepository $repository,
        private readonly FactoryInterface $factory,
        private readonly SubjectTypeResolver $subjectTypes,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, array<string, mixed>> locale => form values */
    public function load(string $type, ?int $id): array
    {
        if (null === $id) {
            return [];
        }

        $data = [];
        foreach ($this->repository->findAllForSubject($type, $id) as $metadata) {
            $data[$metadata->getLocale()] = [
                'metaTitle' => $metadata->getMetaTitle(),
                'metaDescription' => $metadata->getMetaDescription(),
                'canonicalUrl' => $metadata->getCanonicalUrl(),
                'robotsIndex' => $metadata->isRobotsIndex(),
                'robotsFollow' => $metadata->isRobotsFollow(),
                'ogTitle' => $metadata->getOgTitle(),
                'ogDescription' => $metadata->getOgDescription(),
                'ogImage' => $metadata->getOgImage(),
            ];
        }

        return $data;
    }

    /** @param array<string, array<string, mixed>>|null $data */
    public function stash(object $subject, ?array $data): void
    {
        if (null !== $data) {
            $this->pending[spl_object_id($subject)] = $data;
        }
    }

    public function persist(object $subject): void
    {
        $data = $this->pending[spl_object_id($subject)] ?? null;
        unset($this->pending[spl_object_id($subject)]);
        $type = $this->subjectTypes->resolveType($subject);

        if (null === $data || null === $type || !method_exists($subject, 'getId') || null === $subject->getId()) {
            return;
        }

        $id = (int) $subject->getId();
        foreach ($data as $locale => $values) {
            $metadata = $this->repository->findOneForSubject($type, $id, (string) $locale);

            if ($this->isDefault($values)) {
                if (null !== $metadata) {
                    $this->entityManager->remove($metadata);
                }

                continue;
            }

            if (null === $metadata) {
                $metadata = $this->factory->createNew();
                $metadata->setSubjectType($type);
                $metadata->setSubjectId($id);
                $metadata->setLocale((string) $locale);
            }
            $metadata->setMetaTitle($this->text($values['metaTitle'] ?? null));
            $metadata->setMetaDescription($this->text($values['metaDescription'] ?? null));
            $metadata->setCanonicalUrl($this->text($values['canonicalUrl'] ?? null));
            $metadata->setRobotsIndex((bool) ($values['robotsIndex'] ?? false));
            $metadata->setRobotsFollow((bool) ($values['robotsFollow'] ?? false));
            $metadata->setOgTitle($this->text($values['ogTitle'] ?? null));
            $metadata->setOgDescription($this->text($values['ogDescription'] ?? null));
            $metadata->setOgImage($this->text($values['ogImage'] ?? null));
            $this->entityManager->persist($metadata);
        }

        $this->entityManager->flush();
    }

    /** Fixes one stored value from the audit page; the row is dropped when it is back to the defaults. */
    public function updateField(string $type, int $id, string $locale, AuditField $field, string|bool|null $value): void
    {
        $metadata = $this->repository->findOneForSubject($type, $id, $locale);
        $exists = null !== $metadata;

        if (null === $metadata) {
            $metadata = $this->factory->createNew();
            $metadata->setSubjectType($type);
            $metadata->setSubjectId($id);
            $metadata->setLocale($locale);
        }

        match ($field) {
            AuditField::MetaTitle => $metadata->setMetaTitle($this->text($value)),
            AuditField::MetaDescription => $metadata->setMetaDescription($this->text($value)),
            AuditField::RobotsIndex => $metadata->setRobotsIndex((bool) $value),
        };

        if ($this->isDefaultMetadata($metadata)) {
            $exists && $this->entityManager->remove($metadata);
        } else {
            $this->entityManager->persist($metadata);
        }

        $this->entityManager->flush();
    }

    private function isDefaultMetadata(SeoMetadataInterface $metadata): bool
    {
        return $this->isDefault([
            'metaTitle' => $metadata->getMetaTitle(),
            'metaDescription' => $metadata->getMetaDescription(),
            'canonicalUrl' => $metadata->getCanonicalUrl(),
            'ogTitle' => $metadata->getOgTitle(),
            'ogDescription' => $metadata->getOgDescription(),
            'ogImage' => $metadata->getOgImage(),
            'robotsIndex' => $metadata->isRobotsIndex(),
            'robotsFollow' => $metadata->isRobotsFollow(),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function isDefault(array $values): bool
    {
        foreach (self::FIELDS as $field) {
            if (null !== $this->text($values[$field] ?? null)) {
                return false;
            }
        }

        return (bool) ($values['robotsIndex'] ?? false) && (bool) ($values['robotsFollow'] ?? false);
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /** Remembers which rows to drop: Doctrine clears the id of an entity once it is deleted. */
    public function scheduleRemoval(object $subject): void
    {
        $type = $this->subjectTypes->resolveType($subject);

        if (null !== $type && method_exists($subject, 'getId') && null !== $subject->getId()) {
            $this->removals[spl_object_id($subject)] = [$type, (int) $subject->getId()];
        }
    }

    public function removeScheduled(object $subject): void
    {
        $removal = $this->removals[spl_object_id($subject)] ?? null;
        unset($this->removals[spl_object_id($subject)]);

        if (null === $removal) {
            return;
        }

        $this->repository->deleteForSubject($removal[0], $removal[1]);
    }
}
