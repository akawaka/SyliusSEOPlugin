<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Controller;

use Akawaka\SyliusSEOPlugin\Entity\ImageAltInterface;
use Akawaka\SyliusSEOPlugin\Repository\ImageAltRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\TaxonImageInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Saves the alt text of one image for one locale, called by the inline editor of the SEO audit. */
final class ImageAltSaveController
{
    public const CSRF_ID = 'akawaka_seo_image_alt';

    private const TYPES = ['product' => ProductImageInterface::class, 'taxon' => TaxonImageInterface::class];

    /** @param FactoryInterface<ImageAltInterface> $factory */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageAltRepository $repository,
        private readonly FactoryInterface $factory,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $type = $request->request->getString('type');
        $locale = $request->request->getString('locale');
        if (!array_key_exists($type, self::TYPES) || '' === $locale) {
            throw new NotFoundHttpException('Unknown image type or locale.');
        }

        $image = $this->entityManager->getRepository(self::TYPES[$type])->find($request->request->getInt('id'));
        $path = $image?->getPath();
        if (null === $path) {
            throw new NotFoundHttpException('Unknown image.');
        }

        $alt = mb_substr(trim($request->request->getString('alt')), 0, 255);
        $row = $this->repository->findOneBy(['imagePath' => $path, 'locale' => $locale]);

        if ('' === $alt) {
            null !== $row && $this->entityManager->remove($row);
        } elseif (!$row instanceof ImageAltInterface) {
            $row = $this->factory->createNew();
            $row->setImagePath($path);
            $row->setLocale($locale);
            $row->setAlt($alt);
            $this->entityManager->persist($row);
        } else {
            $row->setAlt($alt);
        }

        $this->entityManager->flush();

        return new JsonResponse(['value' => $alt]);
    }
}
