<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\RequestFactory;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as KernalRequest;

class WebsiteRequestFactory extends RequestFactory
{

    /**
     * Creates a new Event\Websites\WebsiteRequest from a ParameterBag of request data
     *
     * @param  ParameterBag $requestData The event request data
     * @param  string       $vertical    The vertical
     * @param  string       $product     The product
     * @return Cygnus\OlyticsBundle\Event\Website\WebsiteRequest
     */
    public function create(ParameterBag $requestData, $vertical, $product)
    {
        foreach (['session', 'event'] as $key) {
            $$key = $this->asArray($requestData->get($key));
        }
        $appendCustomer = ($requestData->get('appendCustomer') === true) ? true : false;
        return new WebsiteRequest($session, $event, $vertical, $product, $appendCustomer);
    }

    /**
     * Gets the request data from a Request object
     * Overloads parent in order to append server-side data to the session
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
     * @todo   Parse the User Agent string into a meaningful object
     * @return array The appended request data
     */
    private function appendSessionData(array $requestData, KernalRequest $request)
    {
        $requestData['session']['ip'] = $request->getClientIp();
        $requestData['session']['ua'] = $request->headers->get('USER_AGENT');
        // @todo Do user agent parse here.
        return $requestData;
    }

}