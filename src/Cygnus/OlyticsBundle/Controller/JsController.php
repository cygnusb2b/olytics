<?php

namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \DateTime;
use \DateTimeZone;
use \JSMin;

class JsController extends Controller
{
    /**
     *
     */
    const RESOURCE_LOC = '@CygnusOlyticsBundle/Resources/public/js';

    /**
     *
     */
    const EXPIRES = 7200;

    /**
     * List of files that should be included in compiled javascript file.
     * @var array
     */
    protected $files = ['uuid.js', 'json2.js'];

    /**
     * Handles requests to load sapience.js or olytics.js
     *
     * @param   string      $file       The file being requested
     * @param   Request     $request
     * @return  Response
     */
    public function indexAction($file, Request $request)
    {
        $this->files[] = sprintf('%s.js', $file);

        $minify = 'prod' === $this->getParameter('kernel.environment');

        $cacheFilename = sprintf(
            '_cache/%s%s.js',
            $file,
            $minify ? '.min' : ''
        );

        if (!file_exists($cacheFilename)) {
            $this->buildFile($cacheFilename, $minify);
        }

        return $this->buildResponse(file_get_contents($cacheFilename), filemtime($cacheFilename), $minify);
    }

    /**
     * Builds and stores the Javascript file contents
     *
     * @param   string  $cacheFilename  The file that should be stored
     * @param   bool    $minify
     */
    protected function buildFile($cacheFilename, $minify)
    {
        $js = array();
        $lastModified = 0;
        $kernel = $this->get('kernel');
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

        $content = implode($js, "\r\n");
        if (true === $minify) {
            $content = JSMin::minify($content);
        }

        file_put_contents($cacheFilename, $content);
    }

    /**
     * Returns a response object
     *
     * @param   string  $content        The file contents being returned
     * @param   int     $lastModified   The last modified date of the contents
     * @return  Response
     */
    protected function buildResponse($content, $lastModified)
    {
        $modified = new DateTime();
        $modified->setTimestamp($lastModified);

        $expires = new DateTime();
        $expires->setTimestamp(time() + self::EXPIRES);

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
