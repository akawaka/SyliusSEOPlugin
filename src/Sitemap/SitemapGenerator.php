<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Sitemap;

use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Akawaka\SyliusSEOPlugin\Repository\SeoMetadataRepository;
use Akawaka\SyliusSEOPlugin\Resolver\AlternateUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Renders the sitemap index and its paginated product / taxon sections for the current channel. */
final class SitemapGenerator
{
    private const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const NS_XHTML = 'http://www.w3.org/1999/xhtml';

    private const NS_IMAGE = 'http://www.google.com/schemas/sitemap-image/1.1';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SeoMetadataRepository $seoRepository,
        private readonly AlternateUrlGenerator $urls,
        private readonly ChannelContextInterface $channelContext,
        private readonly UrlGeneratorInterface $router,
        private readonly CacheManager $imageCache,
        private readonly int $pageSize,
        private readonly ?CmsPages $cmsPages = null,
    ) {
    }

    public function index(): string
    {
        $writer = $this->open('sitemapindex');

        foreach ($this->types() as $type) {
            $pages = max(1, (int) ceil($this->count($type) / $this->pageSize));
            for ($page = 1; $page <= $pages; ++$page) {
                $writer->startElement('sitemap');
                $writer->writeElement('loc', $this->router->generate('akawaka_seo_sitemap_section', ['type' => $type, 'page' => $page], UrlGeneratorInterface::ABSOLUTE_URL));
                $writer->endElement();
            }
        }

        return $this->close($writer);
    }

    public function section(string $type, int $page): ?string
    {
        if (!in_array($type, $this->types(), true) || $page < 1 || $page > max(1, (int) ceil($this->count($type) / $this->pageSize))) {
            return null;
        }

        $channel = $this->channel();
        $writer = $this->open('urlset', true);
        $noIndex = $this->seoRepository->findNoIndexKeys($type);

        if ('page' === $type) {
            $this->writeCmsPages($writer, $channel, $page);

            return $this->close($writer);
        }

        if ('taxon' === $type && 1 === $page) {
            $this->writeHomepage($writer, $channel);
        }

        foreach ($this->subjects($type, $channel, $page) as $subject) {
            $this->writeSubject($writer, $subject, $channel, $noIndex);
        }

        return $this->close($writer);
    }

    /** @return list<string> */
    public function types(): array
    {
        return null === $this->cmsPages ? ['product', 'taxon'] : ['product', 'taxon', 'page'];
    }

    private function writeCmsPages(\XMLWriter $writer, ChannelInterface $channel, int $page): void
    {
        \assert(null !== $this->cmsPages);

        foreach ($this->cmsPages->find($channel, ($page - 1) * $this->pageSize, $this->pageSize) as $cmsPage) {
            $urls = [];
            foreach ($channel->getLocales() as $locale) {
                $code = (string) $locale->getCode();
                $slug = $this->cmsPages->values($cmsPage, $code)['slug'];
                if (null !== $slug) {
                    $urls[str_replace('_', '-', $code)] = $this->router->generate(CmsPages::SHOW_ROUTE, ['_locale' => $code, 'slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);
                }
            }

            $lastmod = method_exists($cmsPage, 'getUpdatedAt') ? $cmsPage->getUpdatedAt() : null;
            foreach ($urls as $url) {
                $this->writeUrl($writer, $url, $urls, $lastmod instanceof \DateTimeInterface ? $lastmod : null, []);
            }
        }
    }

    private function writeHomepage(\XMLWriter $writer, ChannelInterface $channel): void
    {
        $urls = [];
        foreach ($channel->getLocales() as $locale) {
            $code = (string) $locale->getCode();
            $urls[str_replace('_', '-', $code)] = $this->router->generate('sylius_shop_homepage', ['_locale' => $code], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        foreach ($urls as $url) {
            $this->writeUrl($writer, $url, $urls, null, []);
        }
    }

    /** @param array<string, true> $noIndex */
    private function writeSubject(\XMLWriter $writer, object $subject, ChannelInterface $channel, array $noIndex): void
    {
        $id = method_exists($subject, 'getId') ? (int) $subject->getId() : 0;
        $alternates = $this->urls->generate($subject, $channel);
        $lastmod = method_exists($subject, 'getUpdatedAt') ? $subject->getUpdatedAt() : null;
        $images = $subject instanceof ProductInterface ? $this->images($subject) : [];

        foreach ($channel->getLocales() as $locale) {
            $code = (string) $locale->getCode();
            $hreflang = str_replace('_', '-', $code);

            if (!isset($alternates[$hreflang]) || isset($noIndex[$id . '|' . $code])) {
                continue;
            }

            $this->writeUrl($writer, $alternates[$hreflang], $alternates, $lastmod instanceof \DateTimeInterface ? $lastmod : null, $images);
        }
    }

    /**
     * @param array<string, string> $alternates
     * @param list<string> $images
     */
    private function writeUrl(\XMLWriter $writer, string $loc, array $alternates, ?\DateTimeInterface $lastmod, array $images): void
    {
        $writer->startElement('url');
        $writer->writeElement('loc', $loc);

        if (null !== $lastmod) {
            $writer->writeElement('lastmod', $lastmod->format(\DATE_ATOM));
        }

        if (count($alternates) > 1) {
            foreach ($alternates as $hreflang => $url) {
                $writer->startElementNs('xhtml', 'link', null);
                $writer->writeAttribute('rel', 'alternate');
                $writer->writeAttribute('hreflang', $hreflang);
                $writer->writeAttribute('href', $url);
                $writer->endElement();
            }
        }

        foreach ($images as $image) {
            $writer->startElementNs('image', 'image', null);
            $writer->writeElementNs('image', 'loc', null, $image);
            $writer->endElement();
        }

        $writer->endElement();
    }

    /** @return list<string> */
    private function images(ProductInterface $product): array
    {
        $images = [];
        foreach ($product->getImages() as $image) {
            $path = $image->getPath();
            if (null !== $path && count($images) < 5) {
                $images[] = $this->imageCache->getBrowserPath($path, 'sylius_shop_product_original');
            }
        }

        return $images;
    }

    private function count(string $type): int
    {
        if ('page' === $type) {
            return $this->cmsPages?->count($this->channel()) ?? 0;
        }

        return (int) $this->query($type, $this->channel())->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return iterable<object> */
    private function subjects(string $type, ChannelInterface $channel, int $page): iterable
    {
        /** @var list<object> $subjects */
        $subjects = $this->query($type, $channel)
            ->orderBy('s.id')
            ->setFirstResult(($page - 1) * $this->pageSize)
            ->setMaxResults($this->pageSize)
            ->getQuery()
            ->getResult();

        return $subjects;
    }

    private function query(string $type, ChannelInterface $channel): \Doctrine\ORM\QueryBuilder
    {
        $builder = $this->entityManager->createQueryBuilder();

        if ('product' === $type) {
            return $builder->select('s')->from(ProductInterface::class, 's')
                ->innerJoin('s.channels', 'c')
                ->where('c = :channel')->andWhere('s.enabled = true')
                ->setParameter('channel', $channel);
        }

        return $builder->select('s')->from(TaxonInterface::class, 's')
            ->where('s.root = :root')->andWhere('s.parent IS NOT NULL')->andWhere('s.enabled = true')
            ->setParameter('root', $channel->getMenuTaxon());
    }

    private function open(string $root, bool $withNamespaces = false): \XMLWriter
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement($root);
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);

        if ($withNamespaces) {
            $writer->writeAttribute('xmlns:xhtml', self::NS_XHTML);
            $writer->writeAttribute('xmlns:image', self::NS_IMAGE);
        }

        return $writer;
    }

    private function close(\XMLWriter $writer): string
    {
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->channelContext->getChannel();
        \assert($channel instanceof ChannelInterface);

        return $channel;
    }
}
