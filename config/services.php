<?php

declare(strict_types=1);

use Akawaka\SyliusSEOPlugin\EventListener\SeoSubjectDeletedListener;
use Akawaka\SyliusSEOPlugin\EventListener\SeoSubjectSavedListener;
use Akawaka\SyliusSEOPlugin\EventListener\TitleReplacementListener;
use Akawaka\SyliusSEOPlugin\Form\Extension\ProductTypeExtension;
use Akawaka\SyliusSEOPlugin\Form\Extension\TaxonTypeExtension;
use Akawaka\SyliusSEOPlugin\Form\Type\SeoMetadataType;
use Akawaka\SyliusSEOPlugin\Form\Type\SeoSubjectType;
use Akawaka\SyliusSEOPlugin\Persister\SeoMetadataPersister;
use Akawaka\SyliusSEOPlugin\Resolver\AlternateUrlGenerator;
use Akawaka\SyliusSEOPlugin\Resolver\CurrentSubjectProvider;
use Akawaka\SyliusSEOPlugin\Resolver\JsonLdBuilder;
use Akawaka\SyliusSEOPlugin\Resolver\PlaceholderRenderer;
use Akawaka\SyliusSEOPlugin\Resolver\SeoMetadataResolver;
use Akawaka\SyliusSEOPlugin\Resolver\SubjectTypeResolver;
use Akawaka\SyliusSEOPlugin\Sitemap\SitemapGenerator;
use Akawaka\SyliusSEOPlugin\Twig\SeoExtension;
use Akawaka\SyliusSEOPlugin\Audit\SeoAuditor;
use Akawaka\SyliusSEOPlugin\Controller\AuditController;
use Akawaka\SyliusSEOPlugin\Controller\ImageAltSaveController;
use Akawaka\SyliusSEOPlugin\Controller\SeoMetadataSaveController;
use Akawaka\SyliusSEOPlugin\Controller\RobotsTxtController;
use Akawaka\SyliusSEOPlugin\EventListener\ImageAltListener;
use Akawaka\SyliusSEOPlugin\Controller\SitemapController;
use Akawaka\SyliusSEOPlugin\Doctrine\SlugChangeRedirectListener;
use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Akawaka\SyliusSEOPlugin\EventListener\ChannelSavedListener;
use Akawaka\SyliusSEOPlugin\EventListener\NotFoundRedirectListener;
use Akawaka\SyliusSEOPlugin\Form\Type\RedirectType;
use Akawaka\SyliusSEOPlugin\Menu\AdminMenuListener;
use Akawaka\SyliusSEOPlugin\Form\Extension\ChannelTypeExtension;
use Akawaka\SyliusSEOPlugin\Robots\RobotsTxtManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure()->private();

    $services->set(PlaceholderRenderer::class)->args([null]);
    $services->set(AlternateUrlGenerator::class);
    $services->set(SubjectTypeResolver::class)->args(['%akawaka_sylius_seo.subjects%']);

    $services->set(SeoMetadataResolver::class)->args([
        service(SubjectTypeResolver::class),
        service('akawaka_seo.repository.seo_metadata'),
        service(PlaceholderRenderer::class),
        service(AlternateUrlGenerator::class),
        service('sylius.context.channel'),
        service('sylius.context.locale'),
        service('request_stack'),
        '%akawaka_sylius_seo.description_max_length%',
    ]);

    $services->set(CurrentSubjectProvider::class)->args([
        service('request_stack'),
        service('sylius.context.channel'),
        service('sylius.context.locale'),
        service('sylius.repository.product'),
        service('sylius.repository.taxon'),
        service(CmsPages::class)->nullOnInvalid(),
    ]);

    $services->set(JsonLdBuilder::class)->args([
        service('sylius.calculator.product_variant_price'),
        service('sylius.checker.inventory.availability'),
        service('router'),
        service('sylius.context.channel'),
        service('sylius.context.locale'),
    ]);

    $services->set(SitemapGenerator::class)->args([
        service('doctrine.orm.entity_manager'),
        service('akawaka_seo.repository.seo_metadata'),
        service(AlternateUrlGenerator::class),
        service('sylius.context.channel'),
        service('router'),
        service('liip_imagine.cache.manager'),
        '%akawaka_sylius_seo.sitemap.page_size%',
        service(CmsPages::class)->nullOnInvalid(),
    ]);
    $services->set(SitemapController::class)->args([service(SitemapGenerator::class)])->public();

    $services->set(SeoExtension::class)->tag('twig.extension');

    $services->set(TitleReplacementListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => -100]);

    $services->set(SeoMetadataPersister::class)->args([
        service('akawaka_seo.repository.seo_metadata'),
        service('akawaka_seo.factory.seo_metadata'),
        service(SubjectTypeResolver::class),
        service('doctrine.orm.entity_manager'),
    ]);
    $services->set(SeoMetadataType::class)->tag('form.type');
    $services->set(SeoSubjectType::class)->args([service('sylius.repository.locale')])->tag('form.type');
    $services->set(ProductTypeExtension::class)->tag('form.type_extension');
    $services->set(TaxonTypeExtension::class)->tag('form.type_extension');

    $services->set(SeoSubjectDeletedListener::class);

    foreach (['product', 'taxon'] as $resource) {
        $services->get(SeoSubjectDeletedListener::class)
            ->tag('kernel.event_listener', ['event' => sprintf('sylius.%s.pre_delete', $resource), 'method' => 'onPreDelete'])
            ->tag('kernel.event_listener', ['event' => sprintf('sylius.%s.post_delete', $resource), 'method' => 'onPostDelete']);

        foreach (['post_create', 'post_update'] as $event) {
            $services->set(sprintf('akawaka_sylius_seo.listener.%s_%s', $resource, $event), SeoSubjectSavedListener::class)
                ->tag('kernel.event_listener', ['event' => sprintf('sylius.%s.%s', $resource, $event)]);
        }
    }

    $services->set(RobotsTxtManager::class)->args([
        service('akawaka_seo.repository.robots_txt'),
        service('akawaka_seo.factory.robots_txt'),
        service('doctrine.orm.entity_manager'),
    ]);
    $services->set(ChannelTypeExtension::class)->tag('form.type_extension');
    $services->set(RobotsTxtController::class)->args([
        service(RobotsTxtManager::class),
        service('sylius.context.channel'),
        service('router'),
    ])->public();

    foreach (['post_create', 'post_update'] as $event) {
        $services->set('akawaka_sylius_seo.listener.channel_' . $event, ChannelSavedListener::class)
            ->tag('kernel.event_listener', ['event' => 'sylius.channel.' . $event]);
    }

    $services->set(RedirectType::class)->args(['%akawaka_seo.model.redirect.class%', '%akawaka_seo.form.type.redirect.validation_groups%'])->tag('form.type');
    $services->set(NotFoundRedirectListener::class)->args([
        service('akawaka_seo.repository.redirect'),
        service('doctrine.orm.entity_manager'),
    ])->tag('kernel.event_listener', ['event' => 'kernel.exception', 'priority' => 16]);
    $services->set(SlugChangeRedirectListener::class)->args([
        service('router'),
        service('akawaka_seo.repository.redirect'),
        service('akawaka_seo.factory.redirect'),
    ])
        ->tag('doctrine.event_listener', ['event' => 'onFlush']);
    $services->set(AdminMenuListener::class)->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main']);

    $services->set(SeoAuditor::class)->args([
        service('doctrine.orm.entity_manager'),
        service(SeoMetadataResolver::class),
        service('akawaka_seo.repository.seo_metadata'),
        service('akawaka_seo.repository.image_alt'),
        service(CmsPages::class)->nullOnInvalid(),
    ]);
    $services->set(AuditController::class)->args([
        service(SeoAuditor::class),
        service('sylius.repository.channel'),
        service('twig'),
        service('security.csrf.token_manager'),
    ])->public();

    $services->set(ImageAltListener::class)->args([service('akawaka_seo.repository.image_alt')])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => -110]);
    $services->set(SeoMetadataSaveController::class)->args([
        service(SeoMetadataPersister::class),
        service('security.csrf.token_manager'),
    ])->public();
    $services->set(ImageAltSaveController::class)->args([
        service('doctrine.orm.entity_manager'),
        service('akawaka_seo.repository.image_alt'),
        service('akawaka_seo.factory.image_alt'),
        service('security.csrf.token_manager'),
    ])->public();
};
