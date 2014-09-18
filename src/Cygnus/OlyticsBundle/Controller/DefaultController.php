<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return Response::create('Pleased to meet you. My name is Oly.', 403);
    }
}