<?php
namespace Cygnus\OlyticsBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AggregationCompilerPass implements CompilerPassInterface
{
    /**
     * Adds tagged aggregations to the aggregation manager service
     *
     * @param   Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @return  void
     */
    public function process(ContainerBuilder $container)
    {
        $managerId = 'cygnus_olytics.aggregation_manager';
        if (!$container->hasDefinition($managerId)) {
            return;
        }

        // Get the aggregation manager service definition
        $definition = $container->getDefinition($managerId);

        // Get the tagged aggregation services
        $resources = $container->findTaggedServiceIds('cygnus_olytics.aggregation');

        foreach ($resources as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                // Add the aggregation to the manager service definition
                $definition->addMethodCall(
                    'addAggregation',
                    [new Reference($id)]
                );
            }
        }
    }
}
