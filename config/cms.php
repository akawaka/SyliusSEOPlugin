<?php

declare(strict_types=1);

use Akawaka\SyliusSEOPlugin\Integration\Cms\CmsPages;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/** Loaded only when the Akawaka CMS plugin is registered: lets the SEO plugin cover CMS pages as well. */
return static function (ContainerConfigurator $container): void {
    $container->services()->set(CmsPages::class)->args([service('doctrine.orm.entity_manager')]);
};
