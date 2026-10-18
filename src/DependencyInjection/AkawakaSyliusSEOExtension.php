<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class AkawakaSyliusSEOExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('akawaka_sylius_seo.subjects', $config['subjects']);
        $container->setParameter('akawaka_sylius_seo.description_max_length', $config['description_max_length']);
        $container->setParameter('akawaka_sylius_seo.sitemap.page_size', $config['sitemap']['page_size']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        // Optional integration: the SEO plugin never requires the CMS plugin.
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (is_array($bundles) && isset($bundles['AkawakaSyliusCMSPlugin'])) {
            $loader->load('cms.php');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'AkawakaSyliusSEOPlugin' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/../Entity',
                        'prefix' => 'Akawaka\SyliusSEOPlugin\Entity',
                        'alias' => 'AkawakaSyliusSEOPlugin',
                    ],
                ],
            ],
        ]);

        $hooks = ['sylius_shop.base.head' => [
            'akawaka_seo' => ['template' => '@AkawakaSyliusSEOPlugin/shop/head.html.twig', 'priority' => -10],
        ]];

        foreach (['create', 'update'] as $action) {
            $hooks[sprintf('sylius_admin.channel.%s.content.form.sections', $action)] = [
                'akawaka_seo' => ['template' => '@AkawakaSyliusSEOPlugin/admin/channel_section.html.twig', 'priority' => -100],
            ];
            $hooks[sprintf('sylius_admin.product.%s.content.form.side_navigation', $action)] = [
                'akawaka_seo' => ['template' => '@AkawakaSyliusSEOPlugin/admin/product_navigation.html.twig', 'priority' => -100],
            ];
            $hooks[sprintf('sylius_admin.product.%s.content.form.sections', $action)] = [
                'akawaka_seo' => ['template' => '@AkawakaSyliusSEOPlugin/admin/product_section.html.twig', 'priority' => -100],
            ];
            $hooks[sprintf('sylius_admin.taxon.%s.content.sections.form', $action)] = [
                'akawaka_seo' => ['template' => '@AkawakaSyliusSEOPlugin/admin/taxon_section.html.twig', 'priority' => -100],
            ];
        }

        $hooks['akawaka_seo.image_alt.index.content'] = [
            'header' => ['template' => '@AkawakaSyliusSEOPlugin/admin/page_header.html.twig', 'priority' => 200],
            'grid' => ['enabled' => false],
            'report' => ['template' => '@AkawakaSyliusSEOPlugin/admin/image_alt/report.html.twig', 'priority' => 100],
        ];

        $hooks['akawaka_seo.audit.index.content'] = [
            'header' => ['template' => '@AkawakaSyliusSEOPlugin/admin/page_header.html.twig', 'priority' => 200],
            'grid' => ['enabled' => false],
            'report' => ['template' => '@AkawakaSyliusSEOPlugin/admin/audit/report.html.twig', 'priority' => 100],
        ];

        $container->prependExtensionConfig('sylius_twig_hooks', ['hooks' => $hooks]);
    }

    public function getAlias(): string
    {
        return 'akawaka_sylius_seo';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Akawaka\\SyliusSEOPlugin\\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@AkawakaSyliusSEOPlugin/src/Migrations';
    }

    /** @return list<string> */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
