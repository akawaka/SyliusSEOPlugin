<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\Persister;

use Akawaka\SyliusSEOPlugin\Entity\SeoMetadata;
use Akawaka\SyliusSEOPlugin\Persister\SeoMetadataPersister;
use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Akawaka\SyliusSEOPlugin\Resolver\SubjectTypeResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;

final class SeoMetadataPersisterTest extends TestCase
{
    private function product(?int $id): ProductInterface
    {
        $product = $this->createStub(ProductInterface::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    private function persister(?SeoMetadata $existing, EntityManagerInterface $entityManager): SeoMetadataPersister
    {
        $repository = $this->createStub(SeoMetadataRepository::class);
        $repository->method('findOneForSubject')->willReturn($existing);

        return new SeoMetadataPersister(
            $repository,
            new SubjectTypeResolver(['product' => ['class' => ProductInterface::class, 'title_template' => 'T', 'description_template' => null]]),
            $entityManager,
        );
    }

    /** @return array<string, mixed> */
    private function values(string $title = '', bool $index = true, bool $follow = true): array
    {
        return ['metaTitle' => $title, 'metaDescription' => null, 'canonicalUrl' => '', 'robotsIndex' => $index, 'robotsFollow' => $follow, 'ogTitle' => null, 'ogDescription' => null, 'ogImage' => null];
    }

    public function testFilledLocalesAreCreatedAndTrimmed(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (SeoMetadata $m): bool => 'en_US' === $m->getLocale() && 7 === $m->getSubjectId() && 'product' === $m->getSubjectType() && 'Title' === $m->getMetaTitle() && null === $m->getCanonicalUrl(),
        ));
        $entityManager->expects(self::once())->method('flush');

        $persister = $this->persister(null, $entityManager);
        $product = $this->product(7);
        $persister->stash($product, ['en_US' => $this->values('  Title '), 'fr_FR' => $this->values()]);
        $persister->persist($product);
    }

    public function testALocaleBackToDefaultsRemovesItsRow(): void
    {
        $existing = new SeoMetadata('product', 7, 'en_US');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($existing);
        $entityManager->expects(self::never())->method('persist');

        $persister = $this->persister($existing, $entityManager);
        $product = $this->product(7);
        $persister->stash($product, ['en_US' => $this->values()]);
        $persister->persist($product);
    }

    public function testChangingOnlyTheRobotsDirectivesIsStored(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(static fn (SeoMetadata $m): bool => !$m->isRobotsIndex() && $m->isRobotsFollow()));

        $persister = $this->persister(null, $entityManager);
        $product = $this->product(7);
        $persister->stash($product, ['en_US' => $this->values('', false, true)]);
        $persister->persist($product);
    }

    public function testNothingHappensForAnUnsavedSubjectOrWithoutStashedData(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $persister = $this->persister(null, $entityManager);
        $unsaved = $this->product(null);
        $persister->stash($unsaved, ['en_US' => $this->values('Title')]);
        $persister->persist($unsaved);
        $persister->persist($this->product(7));
    }

    public function testRemovalIsRememberedBeforeTheIdIsCleared(): void
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects(self::once())->method('setParameters')->with(['type' => 'product', 'id' => 7])->willReturnSelf();
        $query->expects(self::once())->method('execute');
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('createQuery')->willReturn($query);

        $persister = $this->persister(null, $entityManager);
        $product = $this->product(7);
        $persister->scheduleRemoval($product);
        $persister->removeScheduled($product);
        $persister->removeScheduled($product);
    }
}
