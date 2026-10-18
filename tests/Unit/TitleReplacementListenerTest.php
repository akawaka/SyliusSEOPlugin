<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit;

use Akawaka\SyliusSEOPlugin\EventListener\TitleReplacementListener;
use Akawaka\SyliusSEOPlugin\Twig\SeoExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TitleReplacementListenerTest extends TestCase
{
    private function dispatch(Request $request, Response $response): Response
    {
        (new TitleReplacementListener())(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }

    public function testReplacesTitleAndEscapesIt(): void
    {
        $request = new Request();
        $request->attributes->set(SeoExtension::TITLE_ATTRIBUTE, 'Tom & "Jerry" $1 | Shop');
        $response = new Response('<head><title>Old</title></head>', 200, ['Content-Type' => 'text/html']);

        self::assertSame(
            '<head><title>Tom &amp; &quot;Jerry&quot; $1 | Shop</title></head>',
            $this->dispatch($request, $response)->getContent(),
        );
    }

    public function testLeavesPagesWithoutSeoTitleUntouched(): void
    {
        $response = new Response('<title>Old</title>', 200, ['Content-Type' => 'text/html']);

        self::assertSame('<title>Old</title>', $this->dispatch(new Request(), $response)->getContent());
    }
}
