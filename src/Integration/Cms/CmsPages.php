<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Integration\Cms;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslatableInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * Read-only access to the pages of the Akawaka CMS plugin, when it is installed.
 * The CMS classes are only referred to by name, so this plugin never requires the CMS one.
 * The SEO fields of a page (meta title and description, Open Graph image) are owned by the CMS plugin.
 */
final class CmsPages
{
    public const PAGE_INTERFACE = 'Akawaka\SyliusCMSPlugin\Entity\PageInterface';

    public const PAGE_TRANSLATION_INTERFACE = 'Akawaka\SyliusCMSPlugin\Entity\PageTranslationInterface';

    public const SHOW_ROUTE = 'akawaka_cms_shop_page_show';

    public const EDIT_ROUTE = 'akawaka_cms_admin_page_update';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function count(ChannelInterface $channel): int
    {
        return (int) $this->query($channel)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return list<ResourceInterface&TranslatableInterface> */
    public function find(ChannelInterface $channel, int $offset, int $limit): array
    {
        /** @var list<ResourceInterface&TranslatableInterface> $pages */
        $pages = $this->query($channel)->orderBy('p.id')->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return $pages;
    }

    /** @return (ResourceInterface&TranslatableInterface)|null */
    public function findOneBySlug(string $slug, string $locale, ChannelInterface $channel): ?TranslatableInterface
    {
        $page = $this->query($channel)
            ->innerJoin('p.translations', 't', 'WITH', 't.locale = :locale AND t.slug = :slug')
            ->setParameter('locale', $locale)->setParameter('slug', $slug)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $page instanceof TranslatableInterface && $page instanceof ResourceInterface ? $page : null;
    }

    /** The translation of a page in a locale, null when the page is not translated (never creates one). */
    public function translation(TranslatableInterface $page, string $locale): ?TranslationInterface
    {
        foreach ($page->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /** @return array{title: string, description: string, slug: ?string} resolved values of a page in a locale */
    public function values(TranslatableInterface $page, string $locale): array
    {
        $translation = $this->translation($page, $locale);

        return [
            'title' => $this->read($translation, 'getMetaTitle') ?? $this->read($translation, 'getTitle') ?? '',
            'description' => $this->read($translation, 'getMetaDescription') ?? '',
            'slug' => $this->read($translation, 'getSlug'),
        ];
    }

    public function label(ResourceInterface&TranslatableInterface $page, string $locale): string
    {
        return $this->read($this->translation($page, $locale), 'getTitle') ?? (method_exists($page, 'getCode') ? (string) $page->getCode() : '#' . (string) $page->getId());
    }

    private function read(?TranslationInterface $translation, string $getter): ?string
    {
        $value = null !== $translation && method_exists($translation, $getter) ? $translation->{$getter}() : null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private function query(ChannelInterface $channel): QueryBuilder
    {
        /** @var class-string $pageClass the class only exists when the CMS plugin is installed */
        $pageClass = self::PAGE_INTERFACE;

        return $this->entityManager->createQueryBuilder()
            ->select('p')->from($pageClass, 'p')
            ->innerJoin('p.channels', 'c')
            ->where('c = :channel')->andWhere('p.status = :status')
            ->setParameter('channel', $channel)->setParameter('status', 'published');
    }
}
