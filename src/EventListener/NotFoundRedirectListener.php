<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\EventListener;

use Akawaka\SyliusSEOPlugin\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Turns a 404 into the configured redirection, so live routes are never shadowed. */
final class NotFoundRedirectListener
{
    public function __construct(
        private readonly RedirectRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException || !$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return;
        }

        $path = $request->getPathInfo();
        $redirect = $this->repository->findEnabledBySourcePath(rtrim($path, '/') ?: '/')
            ?? $this->repository->findEnabledBySourcePath($path);
        $target = $redirect?->getTargetUrl();

        if (null === $redirect || null === $target || $target === $path) {
            return;
        }

        $redirect->incrementHits();
        $this->entityManager->flush();

        $query = $request->getQueryString();
        $location = str_starts_with($target, '/') ? $request->getBaseUrl() . $target : $target;
        if (null !== $query && '' !== $query && !str_contains($location, '?')) {
            $location .= '?' . $query;
        }

        $event->setResponse(new RedirectResponse($location, $redirect->getStatus()->value));
    }
}
