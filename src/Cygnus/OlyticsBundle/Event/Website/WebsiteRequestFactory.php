<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\RequestFactory;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as KernalRequest;

class WebsiteRequestFactory extends RequestFactory
{

    public function create(ParameterBag $requestData)
    {
        foreach (['session', 'container', 'event'] as $key) {
            $$key = $this->asArray($requestData->get($key));
        }
        return new WebsiteRequest($session, $container, $event, $requestData->get('pid'));
    }

    /**
     * Gets the request data from a Request object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @param  array The request data
     * @return void
     */
    protected function getDataFromRequest(KernalRequest $request)
    {
        $requestData = RequestFactory::getDataFromRequest($request);
        return $this->appendSessionData($requestData, $request);
    }

    /**
     * Appends additional data to the session such as IP address and User Agent
     *
     * @param  array The request data
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return array The appended request data
     */
    private function appendSessionData(array $requestData, KernalRequest $request)
    {
        $requestData['session']['ip'] = $request->getClientIp();
        $requestData['session']['ua'] = $request->headers->get('USER_AGENT');
        return $requestData;
    }

}