<?php

namespace Cygnus\OlyticsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Cygnus\OlyticsBundle\DependencyInjection\Compiler\AggregationCompilerPass;

class CygnusOlyticsBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // Register aggregations
        $container->addCompilerPass(new AggregationCompilerPass());
    }
}
