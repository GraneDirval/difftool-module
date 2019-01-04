<?php

namespace Playwing\DiffToolBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class DiffToolExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('commands.yml');


        $configuration = new Configuration();
        $config     = $this->processConfiguration($configuration, $configs);

        $comparatorDefinition = $container->getDefinition('demo_data.fixture_data_locator');
        $comparatorDefinition->addArgument($config['paths'] ?? []);

        $dataProviderDefinition = $container->getDefinition('demo_data.entity_serialization_data_provider');
        $entityData = [];
        foreach ($config['entities'] as $className => $options) {
            if (!class_exists($className)) {
                throw new InvalidConfigurationException(sprintf('Class `%s` is not exists', $className));
            }

            $entityData[$className] = [$options['fileName'],$options['ignoredProperties'] ?? []];
        }

        $dataProviderDefinition->addArgument($entityData);


    }
}
