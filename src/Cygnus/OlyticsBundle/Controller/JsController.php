<?php

namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use \DateTime;
use \DateTimeZone;

class JsController extends Controller
{

    const RESOURCE_LOC = '@CygnusOlyticsBundle/Resources/public/js';

    const EXPIRES = 7200;

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

        $expires = new DateTime();
        $expires->setTimestamp(time() + self::EXPIRES);

        $content = implode($js, "\r\n");

        $headers = array(
            'Content-Type'      => 'text/javascript',
            'Content-Length'    => strlen($content),
        );

        $response = new Response($content, 200, $headers);
        $response->setPublic();
        $response->setExpires($expires);
        $response->setMaxAge(self::EXPIRES);
        $response->setSharedMaxAge(self::EXPIRES);
        $response->setLastModified($modified);

        return $response;
    }
}
