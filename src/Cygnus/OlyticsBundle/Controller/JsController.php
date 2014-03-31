<?php

namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use \DateTime;
use \DateTimeZone;

class JsController extends Controller
{

    const RESOURCE_LOC = '@CygnusOlyticsBundle/Resources/public/js';

    protected $files = ['uuid.js', 'json2.js', 'olytics.js'];

    public function indexAction()
    {
        $kernel = $this->get('kernel');

        $js = array();
        $lastModified = 0;
        foreach ($this->files as $file) {
            $fileLocation = self::RESOURCE_LOC . '/' . $file;
            $path = $kernel->locateResource($fileLocation);

            if (file_exists($path)) {
                $modified = filemtime($path);
                if ($modified > $lastModified) {
                    $lastModified = $modified;
                }
                $js[] = file_get_contents($path);
            }
        }

        $modified = new DateTime();
        $modified->setTimestamp($lastModified);

        $content = implode($js, "\r\n");

        $headers = array(
            'Content-Type'      => 'text/javascript',
            'Content-Length'    => strlen($content),
        );

        $response = new Response($content, 200, $headers);
        $response->setPublic();
        $response->setSharedMaxAge(60*60*24);
        $response->setLastModified($modified);

        return $response;
    }
}
