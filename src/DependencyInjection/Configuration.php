<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('akawaka_sylius_seo');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('subjects')
                    ->info('SEO subject types, keyed by type name.')
                    ->useAttributeAsKey('type')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('title_template')->defaultValue('{name} | {channel}')->end()
                            ->scalarNode('description_template')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'product' => [
                            'class' => 'Sylius\Component\Core\Model\ProductInterface',
                            'title_template' => '{name} | {channel}',
                            'description_template' => '{description}',
                        ],
                        'taxon' => [
                            'class' => 'Sylius\Component\Core\Model\TaxonInterface',
                            'title_template' => '{name} | {channel}',
                            'description_template' => '{description}',
                        ],
                    ])
                ->end()
                ->integerNode('description_max_length')->defaultValue(160)->min(1)->end()
                ->arrayNode('sitemap')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('page_size')->info('URLs per sitemap section (the protocol allows 50000).')->defaultValue(5000)->min(1)->max(50000)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
