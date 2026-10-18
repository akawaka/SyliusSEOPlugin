<?php

$bundles = [
    Akawaka\SyliusSEOPlugin\AkawakaSyliusSEOPlugin::class => ['all' => true],
];

// Optional: the CMS plugin is only installed to test the integration (see CmsIntegrationTest).
if (class_exists('Akawaka\SyliusCMSPlugin\AkawakaSyliusCMSPlugin')) {
    $bundles['Akawaka\SyliusCMSPlugin\AkawakaSyliusCMSPlugin'] = ['all' => true];
}

return $bundles;
