<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@AkawakaSyliusSEOPlugin/config/routes/admin.yaml');
    $routes->import('@AkawakaSyliusSEOPlugin/config/routes/shop.yaml');

    // Only when the CMS plugin is installed (integration tests).
    if (class_exists('Akawaka\SyliusCMSPlugin\AkawakaSyliusCMSPlugin')) {
        $routes->import('@AkawakaSyliusCMSPlugin/config/routes/admin.yaml');
        $routes->import('@AkawakaSyliusCMSPlugin/config/routes/shop.yaml');
    }
};
