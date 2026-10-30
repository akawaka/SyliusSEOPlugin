<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit\EventListener;

use Akawaka\SyliusSEOPlugin\EventListener\ImageAltListener;
use Akawaka\SyliusSEOPlugin\Repository\ImageAltRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ImageAltListenerTest extends TestCase
{
    private const HTML = '<img src="/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/photo.webp" alt="Mug" class="x">'
        . '<img class="y" src="/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/other.webp">'
        . '<img src="/build/logo.svg" alt="Logo">';

    /** @param array<string, string> $alts */
    private function apply(array $alts, string $html = self::HTML, string $route = 'sylius_shop_product_show', string $type = 'text/html', int $kernelRequestType = HttpKernelInterface::MAIN_REQUEST): string
    {
        $repository = $this->createStub(ImageAltRepository::class);
        $repository->method('findAlts')->willReturn($alts);

        $request = Request::create('/en_US/products/mug');
        $request->attributes->set('_route', $route);
        $response = new Response($html, 200, ['Content-Type' => $type]);

        (new ImageAltListener($repository))(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $kernelRequestType, $response));

        return (string) $response->getContent();
    }

    public function testStoredAltReplacesTheExistingOneOrIsAdded(): void
    {
        $html = $this->apply(['ab/cd/photo.webp' => 'Photo "de face" & <b>', 'ab/cd/other.webp' => 'Autre']);

        self::assertStringContainsString('<img alt="Photo &quot;de face&quot; &amp; &lt;b&gt;" src="/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/photo.webp" class="x">', $html);
        self::assertStringContainsString('<img alt="Autre" class="y" src="/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/other.webp">', $html);
        self::assertStringContainsString('<img src="/build/logo.svg" alt="Logo">', $html);
    }

    public function testImagesWithoutStoredAltAreUntouched(): void
    {
        self::assertSame(self::HTML, $this->apply([]));
    }

    public function testDollarSignsAndBackslashesInAltAreKeptLiterally(): void
    {
        self::assertStringContainsString('alt="Price $1 \\0"', $this->apply(['ab/cd/photo.webp' => 'Price $1 \\0']));
    }

    public function testAdminPagesNonHtmlResponsesAndSubRequestsAreSkipped(): void
    {
        $alts = ['ab/cd/photo.webp' => 'X'];

        self::assertSame(self::HTML, $this->apply($alts, route: 'sylius_admin_product_index'));
        self::assertSame(self::HTML, $this->apply($alts, route: 'akawaka_seo_admin_audit'));
        self::assertSame(self::HTML, $this->apply($alts, type: 'application/json'));
        self::assertSame(self::HTML, $this->apply($alts, kernelRequestType: HttpKernelInterface::SUB_REQUEST));
    }
}
