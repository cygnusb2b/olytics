<?php

namespace Cygnus\OlyticsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class CygnusOlyticsExtension extends Extension implements PrependExtensionInterface
{

    /**
     * {@inheritDoc}
     */
    public function prepend(ContainerBuilder $container)
    {

        // Would we ever need environment detection here? getParameter('kernel.environment')

        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['DoctrineMongoDBBundle'])) {

            $olyticsConfigs = $container->getExtensionConfig($this->getAlias());
            $olyticsConfig  = $this->processConfiguration(new Configuration(), $olyticsConfigs);

            $doctrineConfig = array(
                'connections'   => array(
                    'olytics'   => $olyticsConfig['connection'],
                ),
                'document_managers' => array(
                    'olytics'   => array(
                        'connection'    => 'olytics',
                        'database'      => 'default',
                    ),
                ),
            );

            $container->prependExtensionConfig('doctrine_mongodb', $doctrineConfig);

        } else {
            throw new \RuntimeException('Doctrine MongoDB not found. Olytics is dependent on Doctrine.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter($this->getAlias() . '.host', $config['host']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    public function getAlias()
    {
        return 'cygnus_olytics';
    }
}
