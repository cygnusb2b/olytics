<?php

namespace Cygnus\OlyticsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('cygnus_olytics');

        $rootNode
            ->children()
                ->arrayNode('connection')
                    ->children()
                        ->scalarNode('server')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('options')
                            ->useAttributeAsKey('key')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('host')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('accounts')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('account')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('products')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()

                    ->end()
                    // ->prototype('array')
                    //     ->children()
                    //         ->arrayNode
                    //         ->prototype('scalar')->end()
                    //     ->end()
                    // ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
