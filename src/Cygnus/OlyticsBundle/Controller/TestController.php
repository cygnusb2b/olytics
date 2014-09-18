<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TestController extends Controller
{
    public function indexAction()
    {
        $serviceHost = $this->container->getParameter('cygnus_olytics.host');

        $curl = $this->get('cygnus_api_suite.curl.kernel');

        $uri = sprintf('http://%s/events/test/test', $serviceHost);
        $content = '{"session":{"id":"ef67479b-de97-45c0-ba21-889ad1e27d33","createdAt":"Thu, 18 Sep 2014 16:17:39 GMT","visitorId":"514fa594-629e-4770-a52d-1efeadaf3bac","customerId":null,"env":{"tz":300,"res":{"width":1920,"height":1200},"windowRes":{"width":1440,"height":748}}},"event":{"action":"view","entity":{"type":"page","clientId":"$hash::http://olytics.cygnus.com/test","keyValues":{"url":"http://olytics.cygnus.com/test","title":"Home | Olytics","type":null},"relatedTo":[]},"data":{},"createdAt":"Thu, 18 Sep 2014 16:17:39 GMT","relatedEntities":[]},"appendCustomer":false}';
        $request = $curl->createRequest($uri, 'POST', [], [], [], [], $content);
        $request->headers->set('Content-Type', 'application/json');

        $response = $curl->handle($request);

        if ($response->isSuccessful()) {
            return $this->render('CygnusOlyticsBundle:Default:index.html.twig', array('serviceHost' => $serviceHost));
        }
        return $response;
    }
}
