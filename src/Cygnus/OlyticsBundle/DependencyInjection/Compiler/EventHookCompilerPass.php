<?php
namespace Cygnus\OlyticsBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EventHookCompilerPass implements CompilerPassInterface
{
    /**
     * Adds tagged event hooks to the event hook manager service
     *
     * @param   Symfony\Component\DependencyInjection\ContainerBuilder $container
     * @return  void
     */
    public function process(ContainerBuilder $container)
    {
        $managerId = 'cygnus_olytics.event_hook_manager';
        if (!$container->hasDefinition($managerId)) {
            return;
        }

        // Get the event hook manager service definition
        $definition = $container->getDefinition($managerId);

        // Get the tagged event hook services
        $resources = $container->findTaggedServiceIds('cygnus_olytics.event_hook');

        foreach ($resources as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                // Add the aggregation to the manager service definition
                $definition->addMethodCall(
                    'addEventHook',
                    [new Reference($id)]
                );
            }
        }
    }
}
