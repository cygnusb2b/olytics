<?php

namespace Cygnus\OlyticsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Cygnus\OlyticsBundle\DependencyInjection\Compiler\AggregationCompilerPass;
use Cygnus\OlyticsBundle\DependencyInjection\Compiler\EventHookCompilerPass;

class CygnusOlyticsBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // Register aggregations
        $container->addCompilerPass(new AggregationCompilerPass());

        // Register event hooks
        $container->addCompilerPass(new EventHookCompilerPass());
    }
}
