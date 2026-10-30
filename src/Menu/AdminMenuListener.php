<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $seo = $event->getMenu()
            ->addChild('akawaka_seo')
            ->setLabel('akawaka_seo.menu.seo')
            ->setLabelAttribute('icon', 'tabler:search')
        ;

        $seo
            ->addChild('audit', ['route' => 'akawaka_seo_admin_audit'])
            ->setLabel('akawaka_seo.menu.audit')
            ->setLabelAttribute('icon', 'tabler:list-check')
        ;

        $seo
            ->addChild('redirects', ['route' => 'akawaka_seo_admin_redirect_index', 'extras' => ['routes' => [
                ['route' => 'akawaka_seo_admin_redirect_create'],
                ['route' => 'akawaka_seo_admin_redirect_update'],
            ]]])
            ->setLabel('akawaka_seo.menu.redirects')
            ->setLabelAttribute('icon', 'tabler:arrows-right-left')
        ;
    }
}
