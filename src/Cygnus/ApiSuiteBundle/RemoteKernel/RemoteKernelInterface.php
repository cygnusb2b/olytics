<?php
namespace Cygnus\ApiSuiteBundle\RemoteKernel;

use Symfony\Component\HttpKernel\HttpKernelInterface;

interface RemoteKernelInterface extends HttpKernelInterface 
{    
    public function createRequest($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null);
    public function getClient();
}