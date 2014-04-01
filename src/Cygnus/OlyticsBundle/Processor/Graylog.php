<?php
namespace Cygnus\OlyticsBundle\Processor;

use Symfony\Component\DependencyInjection\Container;

class Graylog
{

    private $request;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->request = $this->container->get('request');
    }

    public function processRecord(array $record)
    {
        $record['channel']  = 'olytics';
        

        $extra = array(
            'environment'   => $this->container->getParameter('kernel.environment'),
            'clientIp'      => $this->request->getClientIp(),
            'serviceHost'   => $this->container->getParameter('cygnus_olytics.host'),
            'httpHost'      => $this->request->getHttpHost(),
            'requestMethod' => $this->request->getMethod(),
            'requestUri'    => $this->request->getRequestUri(),
            'requestContent'=> $this->request->getContent(),
            'referrer'      => $this->request->server->get('HTTP_REFERER'),
        );
        $record['extra'] = array_merge($record['extra'], $extra);
        return $record;
    }
}