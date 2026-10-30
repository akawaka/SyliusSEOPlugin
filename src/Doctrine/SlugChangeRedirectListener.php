<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Doctrine;

use Akawaka\SyliusSEOPlugin\Entity\RedirectInterface;
use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Akawaka\SyliusSEOPlugin\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\RouterInterface;

/** Creates a 301 from the old storefront URL whenever the slug of a product or taxon changes. */
final class SlugChangeRedirectListener
{
    private const ROUTES = [
        ProductTranslationInterface::class => 'sylius_shop_product_show',
        TaxonTranslationInterface::class => 'sylius_shop_product_index',
        // Only matches when the Akawaka CMS plugin is installed; its route is then generated like the others.
        CmsPages::PAGE_TRANSLATION_INTERFACE => CmsPages::SHOW_ROUTE,
    ];

    /** @param FactoryInterface<RedirectInterface> $redirectFactory */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RedirectRepository $redirects,
        private readonly FactoryInterface $redirectFactory,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        \assert($entityManager instanceof EntityManagerInterface);
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $route = $this->routeFor($entity);
            $changes = $unitOfWork->getEntityChangeSet($entity);

            if (null === $route || !isset($changes['slug']) || !method_exists($entity, 'getLocale')) {
                continue;
            }

            $slugChange = $changes['slug'];
            \assert(is_array($slugChange));
            [$old, $new] = $slugChange;
            if (!is_string($old) || !is_string($new) || '' === $old || '' === $new || $old === $new) {
                continue;
            }

            $oldPath = $this->path($route, $old, (string) $entity->getLocale());
            $newPath = $this->path($route, $new, (string) $entity->getLocale());
            if (null !== $oldPath && null !== $newPath) {
                $this->register($entityManager, $oldPath, $newPath);
            }
        }
    }

    private function register(EntityManagerInterface $entityManager, string $oldPath, string $newPath): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata($this->redirects->getClassName());
        $repository = $this->redirects;

        // The new URL is live again: a redirection away from it would loop.
        foreach ($repository->findBy(['sourcePath' => $newPath]) as $obsolete) {
            $unitOfWork->scheduleForDelete($obsolete);
        }

        // Older redirections pointing to the previous URL must follow the move (no chains).
        foreach ($repository->findBy(['targetUrl' => $oldPath]) as $chained) {
            \assert($chained instanceof RedirectInterface);
            if ($unitOfWork->isScheduledForDelete($chained)) {
                continue;
            }

            $chained->setTargetUrl($newPath);
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $chained);
        }

        $redirect = $repository->findOneBy(['sourcePath' => $oldPath]);
        if ($redirect instanceof RedirectInterface) {
            $redirect->setTargetUrl($newPath);
            $redirect->setEnabled(true);
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $redirect);

            return;
        }

        $redirect = $this->redirectFactory->createNew();
        $redirect->setSourcePath($oldPath);
        $redirect->setTargetUrl($newPath);
        $redirect->setAutomatic(true);
        $entityManager->persist($redirect);
        $unitOfWork->computeChangeSet($metadata, $redirect);
    }

    private function routeFor(object $entity): ?string
    {
        foreach (self::ROUTES as $class => $route) {
            if ($entity instanceof $class) {
                return $route;
            }
        }

        return null;
    }

    private function path(string $route, string $slug, string $locale): ?string
    {
        try {
            return $this->router->generate($route, ['slug' => $slug, '_locale' => $locale]);
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
