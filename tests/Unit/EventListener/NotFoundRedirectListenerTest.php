<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\EventListener;

use Akawaka\SyliusSEOPlugin\Entity\Redirect;
use Akawaka\SyliusSEOPlugin\Enum\RedirectStatus;
use Akawaka\SyliusSEOPlugin\EventListener\NotFoundRedirectListener;
use Akawaka\SyliusSEOPlugin\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class NotFoundRedirectListenerTest extends TestCase
{
    private function redirect(string $source, string $target, RedirectStatus $status = RedirectStatus::Permanent): Redirect
    {
        $redirect = new Redirect();
        $redirect->setSourcePath($source);
        $redirect->setTargetUrl($target);
        $redirect->setStatus($status);

        return $redirect;
    }

    private function dispatch(?Redirect $redirect, Request $request, \Throwable $error = new NotFoundHttpException(), int $type = HttpKernelInterface::MAIN_REQUEST): ExceptionEvent
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findEnabledBySourcePath')->willReturnCallback(static fn (string $path): ?Redirect => $redirect?->getSourcePath() === $path ? $redirect : null);

        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, $type, $error);
        (new NotFoundRedirectListener($repository, $this->createStub(EntityManagerInterface::class)))($event);

        return $event;
    }

    public function testA404IsTurnedIntoTheConfiguredRedirectAndCounted(): void
    {
        $redirect = $this->redirect('/en_US/old', '/en_US/new', RedirectStatus::Temporary);
        $response = $this->dispatch($redirect, Request::create('/en_US/old'))->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/en_US/new', $response->getTargetUrl());
        self::assertSame(1, $redirect->getHits());
    }

    public function testQueryStringIsKeptAndTrailingSlashIsIgnored(): void
    {
        $response = $this->dispatch($this->redirect('/en_US/old', 'https://example.com/new'), Request::create('/en_US/old/?utm=1'))->getResponse();

        self::assertNotNull($response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://example.com/new?utm=1', $response->headers->get('Location'));
    }

    public function testNothingHappensForOtherErrorsMethodsOrSubRequests(): void
    {
        $redirect = $this->redirect('/en_US/old', '/en_US/new');

        self::assertNull($this->dispatch($redirect, Request::create('/en_US/old'), new \RuntimeException())->getResponse());
        self::assertNull($this->dispatch($redirect, Request::create('/en_US/old', 'POST'))->getResponse());
        self::assertNull($this->dispatch($redirect, Request::create('/en_US/old'), type: HttpKernelInterface::SUB_REQUEST)->getResponse());
        self::assertNull($this->dispatch($redirect, Request::create('/en_US/unknown'))->getResponse());
        self::assertSame(0, $redirect->getHits());
    }

    public function testARedirectToItsOwnPathIsIgnored(): void
    {
        self::assertNull($this->dispatch($this->redirect('/loop', '/loop'), Request::create('/loop'))->getResponse());
    }
}
