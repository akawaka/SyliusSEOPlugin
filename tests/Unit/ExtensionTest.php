<?php

declare(strict_types=1);

namespace Tests\Akawaka\SyliusSEOPlugin\Unit;

use Akawaka\SyliusSEOPlugin\AkawakaSyliusSEOPlugin;
use Akawaka\SyliusSEOPlugin\DependencyInjection\AkawakaSyliusSEOExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionTest extends TestCase
{
    public function testPluginExposesExtensionWithSyliusNamingConvention(): void
    {
        self::assertInstanceOf(AkawakaSyliusSEOExtension::class, (new AkawakaSyliusSEOPlugin())->getContainerExtension());
    }

    public function testLoadsDefaultSubjectsAndServices(): void
    {
        $container = new ContainerBuilder();
        (new AkawakaSyliusSEOExtension())->load([], $container);

        self::assertSame(['product', 'taxon'], array_keys($container->getParameter('akawaka_sylius_seo.subjects')));
        self::assertSame(160, $container->getParameter('akawaka_sylius_seo.description_max_length'));
        self::assertTrue($container->hasDefinition(\Akawaka\SyliusSEOPlugin\Resolver\SeoMetadataResolver::class));
    }

    public function testPrependsHeadHookAndDoctrineMapping(): void
    {
        $container = new ContainerBuilder();
        (new AkawakaSyliusSEOExtension())->prepend($container);

        self::assertArrayHasKey('sylius_shop.base.head', $container->getExtensionConfig('sylius_twig_hooks')[0]['hooks']);
        self::assertArrayHasKey('AkawakaSyliusSEOPlugin', $container->getExtensionConfig('doctrine')[0]['orm']['mappings']);
    }

    public function testCmsIntegrationIsOnlyLoadedWhenTheCmsPluginIsRegistered(): void
    {
        $without = new ContainerBuilder();
        $without->setParameter('kernel.bundles', ['AkawakaSyliusSEOPlugin' => AkawakaSyliusSEOPlugin::class]);
        (new AkawakaSyliusSEOExtension())->load([], $without);

        $with = new ContainerBuilder();
        $with->setParameter('kernel.bundles', ['AkawakaSyliusCMSPlugin' => 'Akawaka\\SyliusCMSPlugin\\AkawakaSyliusCMSPlugin']);
        (new AkawakaSyliusSEOExtension())->load([], $with);

        self::assertFalse($without->hasDefinition(\Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages::class));
        self::assertTrue($with->hasDefinition(\Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages::class));
    }
}
