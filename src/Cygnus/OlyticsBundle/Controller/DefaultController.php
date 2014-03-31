<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $serviceHost = $this->container->getParameter('cygnus_olytics.host');
        return $this->render('CygnusOlyticsBundle:Default:index.html.twig', array('serviceHost' => $serviceHost));
    }
}