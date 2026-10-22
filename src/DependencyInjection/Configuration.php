<?php

namespace Wexample\SymfonyShop\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_shop');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('digital')
                    ->info('How a digital product delivers its files once paid: a link per file, signed and counted. A product overrides them in its metadata (download_lifetime_days, download_max_uses).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('download_lifetime_days')->defaultValue(30)->min(0)->info('Days a link works; 0 for no end.')->end()
                        ->integerNode('download_max_uses')->defaultValue(5)->min(0)->info('Downloads a link allows; 0 for no limit.')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
