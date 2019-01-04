<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 04.01.19
 * Time: 12:29
 */

namespace Playwing\DiffToolBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('diff_tool');

        $rootNode
            ->children()
            ->arrayNode('paths')
            ->prototype('scalar')->end()
            ->end()
            ->arrayNode('entities')
            ->prototype('array')
            ->children()
            ->scalarNode('fileName')->end()
            ->arrayNode('ignoredProperties')
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();


        return $treeBuilder;
    }
}