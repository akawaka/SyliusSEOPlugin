<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Controller;

use Akawaka\SyliusSEOPlugin\Entity\ImageAlt;
use Akawaka\SyliusSEOPlugin\Repository\ImageAltRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\TaxonImageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/** Lists the product / taxon images with their alt text per locale and saves the edited ones. */
final class ImageAltController
{
    public const CSRF_ID = 'akawaka_seo_image_alt';

    private const PER_PAGE = 30;

    private const TYPES = ['product' => ProductImageInterface::class, 'taxon' => TaxonImageInterface::class];

    /** @param ChannelRepositoryInterface<\Sylius\Component\Channel\Model\ChannelInterface> $channelRepository */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageAltRepository $repository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $type = array_key_exists($request->query->getString('type'), self::TYPES) ? $request->query->getString('type') : 'product';
        $locales = $this->locales();
        $locale = in_array($request->query->getString('locale'), $locales, true) ? $request->query->getString('locale') : ($locales[0] ?? 'en_US');
        $missingOnly = $request->query->getBoolean('missing');
        $page = max(1, $request->query->getInt('page', 1));
        $query = ['type' => $type, 'locale' => $locale, 'missing' => $missingOnly ? 1 : 0, 'page' => $page];

        if ($request->isMethod('POST')) {
            if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_token')))) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
            $this->save($type, $locale, $request->request->all('alt'));

            return new RedirectResponse('?' . http_build_query($query));
        }

        $builder = $this->entityManager->createQueryBuilder()
            ->select('i')->from(self::TYPES[$type], 'i')
            ->leftJoin(ImageAlt::class, 'a', 'WITH', 'a.imagePath = i.path AND a.locale = :locale')
            ->where('i.path IS NOT NULL')
            ->setParameter('locale', $locale);
        if ($missingOnly) {
            $builder->andWhere('a.id IS NULL');
        }

        $total = (int) (clone $builder)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();
        /** @var list<ProductImageInterface|TaxonImageInterface> $images */
        $images = $builder->orderBy('i.id')->setFirstResult(($page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE)->getQuery()->getResult();

        $paths = array_values(array_filter(array_map(static fn ($image): ?string => $image->getPath(), $images)));

        return new Response($this->twig->render('@AkawakaSyliusSEOPlugin/admin/image_alt/index.html.twig', [
            'type' => $type,
            'locale' => $locale,
            'locales' => $locales,
            'missing_only' => $missingOnly,
            'images' => $images,
            'alts' => $this->repository->findAlts($paths, $locale),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
            'token' => $this->csrf->getToken(self::CSRF_ID)->getValue(),
        ]));
    }

    /** @param array<array-key, mixed> $submitted image id => alt */
    private function save(string $type, string $locale, array $submitted): void
    {
        $ids = array_map('intval', array_keys($submitted));
        if ([] === $ids) {
            return;
        }

        /** @var list<ProductImageInterface|TaxonImageInterface> $images */
        $images = $this->entityManager->createQueryBuilder()
            ->select('i')->from(self::TYPES[$type], 'i')->where('i.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult();

        $paths = array_values(array_filter(array_map(static fn ($image): ?string => $image->getPath(), $images)));
        $existing = [];
        foreach ($this->repository->findBy(['imagePath' => $paths, 'locale' => $locale]) as $row) {
            $existing[$row->getImagePath()] = $row;
        }

        foreach ($images as $image) {
            $path = $image->getPath();
            $alt = $submitted[$image->getId()] ?? null;
            if (null === $path || !is_string($alt)) {
                continue;
            }

            $alt = mb_substr(trim($alt), 0, 255);
            $row = $existing[$path] ?? null;
            if ('' === $alt) {
                null !== $row && $this->entityManager->remove($row);
            } elseif (null === $row) {
                $this->entityManager->persist(new ImageAlt($path, $locale, $alt));
            } else {
                $row->setAlt($alt);
            }
        }

        $this->entityManager->flush();
    }

    /** @return list<string> */
    private function locales(): array
    {
        $codes = [];
        foreach ($this->channelRepository->findAll() as $channel) {
            if ($channel instanceof ChannelInterface) {
                foreach ($channel->getLocales() as $locale) {
                    $codes[(string) $locale->getCode()] = true;
                }
            }
        }

        return array_keys($codes);
    }
}
