<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class TestController extends Controller
{
    public function indexAction()
    {

        $content = base64_encode('{"session":{"id":"ef67479b-de97-45c0-ba21-889ad1e27d33","createdAt":"Thu, 18 Sep 2014 16:17:39 GMT","visitorId":"514fa594-629e-4770-a52d-1efeadaf3bac","customerId":null,"env":{"tz":300,"res":{"width":1920,"height":1200},"windowRes":{"width":1440,"height":748}}},"event":{"action":"view","entity":{"type":"page","clientId":"$hash::http://olytics.cygnus.com/test","keyValues":{"url":"http://olytics.cygnus.com/test","title":"Home | Olytics","type":null},"relatedTo":[]},"data":{},"createdAt":"Thu, 18 Sep 2014 16:17:39 GMT","relatedEntities":[]},"appendCustomer":false}');

        $response = $this->forward('CygnusOlyticsBundle:Event:index', ['account' => 'test', 'product' => 'test'], ['enc' => $content]);

        if ($response->isSuccessful()) {
            $serviceHost = $this->container->getParameter('cygnus_olytics.host');
            return $this->render('CygnusOlyticsBundle:Default:index.html.twig', array('serviceHost' => $serviceHost));
        }
        return $response;
    }
}
