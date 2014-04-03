<?php
namespace Cygnus\ApiSuiteBundle\ApiClient;

use 
    Cygnus\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

interface ApiClientInterface
{
    /**
     * Sets the remote RemoteKernelInterface for sending Request objects and returning Response objects
     *
     * @param  Cygnus\ApiSuiteBundle\RemoteKernel\RemoteKernelInterface $httpKernel
     * @return void
     */
    public function setRemoteHttpKernel(RemoteKernelInterface $httpKernel);

    /**
     * Takes a Request object and performs the request via the RemoteKernelInterface
     * This should return a Response object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\Response
     * @throws \Exception On errors
     */
    public function doRequest(Request $request);

    public function setConfig(array $config);

    public function hasValidConfig();
}