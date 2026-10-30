<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Audit;

use Akawaka\SyliusSEOPlugin\Enum\AuditField;
use Akawaka\SyliusSEOPlugin\Enum\AuditRule;
use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Akawaka\SyliusSEOPlugin\Repository\ImageAltRepository;
use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Akawaka\SyliusSEOPlugin\Resolver\SeoMetadataResolver;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslatableInterface;

/** Applies the SEO rules to the catalog of one channel and locale. */
final class SeoAuditor
{
    public const TITLE_MIN = 30;

    public const TITLE_MAX = 60;

    public const DESCRIPTION_MIN = 70;

    public const DESCRIPTION_MAX = 160;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SeoMetadataResolver $resolver,
        private readonly SeoMetadataRepository $repository,
        private readonly ImageAltRepository $imageAlts,
        private readonly ?CmsPages $cmsPages = null,
    ) {
    }

    /** @return list<AuditIssue> */
    public function audit(ChannelInterface $channel, string $locale, int $limit = 1000): array
    {
        $issues = [];
        $titles = [];
        $fields = [];
        $titleValues = [];

        foreach ($this->subjects($channel, $limit) as [$type, $subject]) {
            if ($subject instanceof TranslatableInterface) {
                $subject->setCurrentLocale($locale);
            }

            $metadata = $this->resolver->resolveFor($subject, $channel, $locale);
            if (null === $metadata) {
                continue;
            }

            $id = (int) $subject->getId();
            $label = $this->label($subject);
            $stored = $this->repository->findOneForSubject($type, $id, $locale);
            $description = $stored?->getMetaDescription() ?? $metadata->description;
            $add = function (AuditRule $rule) use (&$issues, &$fields, $type, $id, $label, $metadata, $description): void {
                $issues[] = $this->fieldIssue($fields, $type, $id, $label, $rule, $metadata->title, $description);
            };

            // The resolved description is truncated, so the length of an override is read from the stored value.
            $titleLength = mb_strlen($metadata->title);
            $this->checkTexts($add, $titleLength, mb_strlen($description));

            if (str_starts_with($metadata->robots, 'noindex')) {
                $add(AuditRule::NoIndex);
            }

            if ($subject instanceof ProductInterface && $subject->getImages()->isEmpty()) {
                $add(AuditRule::ProductWithoutImage);
            }

            if ($subject instanceof ImagesAwareInterface) {
                foreach ($this->imagesWithoutAlt($subject, $locale) as $position => $image) {
                    $issues[] = new AuditIssue($type, $id, $label, AuditRule::ImageWithoutAlt, (int) $image->getId(), $image->getPath(), $image->getType(), $position);
                }
            }

            if ($titleLength > 0) {
                $titles[mb_strtolower($metadata->title)][] = [$type, $id, $label];
                $titleValues[$type . ':' . $id] = $metadata->title;
            }
        }

        foreach ($this->cmsIssues($channel, $locale, $limit, $titles) as $issue) {
            $issues[] = $issue;
        }

        foreach ($titles as $group) {
            if (count($group) > 1) {
                foreach ($group as [$type, $id, $label]) {
                    $issues[] = $this->fieldIssue($fields, $type, $id, $label, AuditRule::TitleDuplicate, $titleValues[$type . ':' . $id] ?? '', '');
                }
            }
        }

        return $issues;
    }

    /**
     * A subject gets one editable field per stored value: a second rule on the same field (a short and duplicated
     * title) is only reported. CMS pages own their SEO fields, so they never get one.
     *
     * @param array<string, true> $fields
     */
    private function fieldIssue(array &$fields, string $type, int $id, string $label, AuditRule $rule, string $title, string $description): AuditIssue
    {
        $field = $rule->field();
        if (null !== $field) {
            $key = $type . ':' . $id . ':' . $field->value;
            if ('page' === $type || isset($fields[$key])) {
                $field = null;
            } else {
                $fields[$key] = true;
            }
        }

        $value = match ($field) {
            AuditField::MetaTitle => $title,
            AuditField::MetaDescription => $description,
            AuditField::RobotsIndex => '',
            null => null,
        };

        return new AuditIssue($type, $id, $label, $rule, field: $field, value: $value);
    }

    /**
     * Pages of the CMS plugin, when installed. Their SEO fields belong to the CMS plugin, so they are only read.
     *
     * @param array<string, list<array{0: string, 1: int, 2: string}>> $titles
     *
     * @return list<AuditIssue>
     */
    private function cmsIssues(ChannelInterface $channel, string $locale, int $limit, array &$titles): array
    {
        if (null === $this->cmsPages) {
            return [];
        }

        $issues = [];
        foreach ($this->cmsPages->find($channel, 0, $limit) as $page) {
            $id = (int) $page->getId();
            $label = $this->cmsPages->label($page, $locale);
            $values = $this->cmsPages->values($page, $locale);
            $add = static function (AuditRule $rule) use (&$issues, $id, $label): void {
                $issues[] = new AuditIssue('page', $id, $label, $rule);
            };

            $this->checkTexts($add, mb_strlen($values['title']), mb_strlen($values['description']));
            if ('' !== $values['title']) {
                $titles[mb_strtolower($values['title'])][] = ['page', $id, $label];
            }
        }

        return $issues;
    }

    /** @return array<int, ImageInterface> images without alt text for the locale (one per path), keyed by their 1-based rank among the images of the subject */
    private function imagesWithoutAlt(ImagesAwareInterface $subject, string $locale): array
    {
        $byPath = [];
        $rank = 0;
        foreach ($subject->getImages() as $image) {
            ++$rank;
            null !== $image->getPath() && $byPath[$image->getPath()] ??= [$rank, $image];
        }

        $missing = [];
        foreach (array_diff_key($byPath, $this->imageAlts->findAlts(array_keys($byPath), $locale)) as [$position, $image]) {
            $missing[$position] = $image;
        }

        return $missing;
    }

    /** @param callable(AuditRule): void $add */
    private function checkTexts(callable $add, int $titleLength, int $descriptionLength): void
    {
        foreach ([AuditRule::forTitleLength($titleLength), AuditRule::forDescriptionLength($descriptionLength)] as $rule) {
            null !== $rule && $add($rule);
        }
    }

    /** @return iterable<array{0: string, 1: ResourceInterface}> */
    private function subjects(ChannelInterface $channel, int $limit): iterable
    {
        /** @var list<ProductInterface> $products */
        $products = $this->entityManager->createQueryBuilder()
            ->select('p')->from(ProductInterface::class, 'p')
            ->innerJoin('p.channels', 'c')
            ->where('c = :channel')->andWhere('p.enabled = true')
            ->setParameter('channel', $channel)
            ->orderBy('p.id')->setMaxResults($limit)
            ->getQuery()->getResult();

        foreach ($products as $product) {
            yield ['product', $product];
        }

        /** @var list<TaxonInterface> $taxons */
        $taxons = $this->entityManager->createQueryBuilder()
            ->select('t')->from(TaxonInterface::class, 't')
            ->where('t.root = :root')->andWhere('t.parent IS NOT NULL')->andWhere('t.enabled = true')
            ->setParameter('root', $channel->getMenuTaxon())
            ->orderBy('t.id')->setMaxResults($limit)
            ->getQuery()->getResult();

        foreach ($taxons as $taxon) {
            yield ['taxon', $taxon];
        }
    }

    private function label(ResourceInterface $subject): string
    {
        return method_exists($subject, 'getName') ? (string) $subject->getName() : '#' . (string) $subject->getId();
    }
}
