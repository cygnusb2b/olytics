<?php

namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use \DateTime;
use \DateTimeZone;
use \JSMin;

class JsController extends Controller
{

    const RESOURCE_LOC = '@CygnusOlyticsBundle/Resources/public/js';

    const EXPIRES = 7200;

    protected $files = ['uuid.js', 'json2.js', 'olytics.js'];

    public function sapienceAction()
    {
        $this->files = ['uuid.js', 'json2.js', 'sapience.js'];
        return $this->indexAction();
    }

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
        if ('prod' === $this->getParameter('kernel.environment')) {
            $content = JSMin::minify($content);
        }

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
