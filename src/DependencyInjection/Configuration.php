<?php

declare(strict_types=1);

namespace Akawaka\SyliusSEOPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_SUBJECTS = [
        'product' => [
            'class' => 'Sylius\\Component\\Core\\Model\\ProductInterface',
            'title_template' => '{name} | {channel}',
            'description_template' => '{description}',
        ],
        'taxon' => [
            'class' => 'Sylius\\Component\\Core\\Model\\TaxonInterface',
            'title_template' => '{name} | {channel}',
            'description_template' => '{description}',
        ],
    ];

    /**
     * @param array<string, array{class?: ?string, title_template?: ?string, description_template?: ?string}> $subjects
     *
     * @return array<string, array{class: string, title_template: string, description_template: ?string}>
     */
    public static function withDefaultSubjects(array $subjects): array
    {
        $merged = self::DEFAULT_SUBJECTS;
        foreach ($subjects as $type => $options) {
            $merged[$type] = [...($merged[$type] ?? ['class' => null, 'title_template' => '{name} | {channel}', 'description_template' => null]), ...array_filter($options, static fn (?string $value): bool => null !== $value)];
            if (!is_string($merged[$type]['class'] ?? null) || '' === $merged[$type]['class']) {
                throw new \InvalidArgumentException(sprintf('The "class" option of akawaka_sylius_seo.subjects.%s is required.', $type));
            }
        }

        /** @var array<string, array{class: string, title_template: string, description_template: ?string}> $merged */
        return $merged;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('akawaka_sylius_seo');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('subjects')
                    ->info('SEO subject types, keyed by type name. The product and taxon defaults are kept when only some of their options are set.')
                    ->useAttributeAsKey('type')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->defaultNull()->end()
                            ->scalarNode('title_template')->defaultNull()->end()
                            ->scalarNode('description_template')->defaultNull()->end()
                        ->end()
                    ->end()
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
