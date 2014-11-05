<?php

namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Cygnus\OlyticsBundle\Model\Exception\InvalidModelException;
use \Exception;

class EventController extends Controller
{
    public function indexAction($account, $product)
    {
        // This is legacy handling until the websites are switched
        // REMOVE once complete
        if ($account != 'test') {
            $accounts = ['acbm', 'cygnus'];
            if (!in_array($account, $accounts)) {
                // Account is a legacy Vertical key
                $acbmProductMap = ['fcp', 'sdce', 'fl', 'ooh'];
                $account = (in_array($product, $acbmProductMap)) ? 'acbm' : 'cygnus';
            }
        }

        // Get the incoming request
        $request = $this->get('request');

        // Handle OPTIONS method for CORS
        if ($request->getMethod() == 'OPTIONS') {
            return $this->handleResponse($request);
        }

        // Handle bots
        $botDetector = $this->get('cygnus_olytics.bot_detector');
        $ua = $request->headers->get('USER_AGENT');
        if ($botDetector->hasMatch($ua)) {
            // Do logging or storage of bot data here

            // Return response
            $responseBody = ['created' => false, 'reason' => 'robot'];
            return $this->handleResponse($request, 202, $responseBody);
        }

        // Load the website event request manager
        $requestManager = $this->get('cygnus_olytics.events.website.request_manager');
        try {
            // Create and manage event from the HTTP request
            $requestManager->createAndManage($request, $account, $product);

            // Persist to the DB
            $requestManager->persist();

            $responseBody = ['created' => true];
            $responseCode = 201;
        } catch (InvalidModelException $e) {
            $responseBody = ['created' => false, 'reason' => 'invalid'];
            $responseCode = 400;
            $this->notifyError($e, $request, $account, $product);
        } catch (Exception $e) {
            $responseBody = ['created' => false, 'reason'  => 'exception'];
            $responseCode = 500;
            $this->notifyError($e, $request, $account, $product);
        }
        // Return response
        return $this->handleResponse($request, $responseCode, $responseBody);


    }

    public function notifyError(Exception $e, Request $request, $accountKey, $groupKey)
    {
        if (extension_loaded('newrelic')) {

            $requestManager = $this->get('cygnus_olytics.events.website.request_manager');
            $eventRequest = $requestManager->createRequestFromFactory($request, $accountKey, $groupKey);

            newrelic_add_custom_parameter('accountKey', $accountKey);
            newrelic_add_custom_parameter('groupKey', $groupKey);
            newrelic_add_custom_parameter('exceptionClass', get_class($e));
            newrelic_add_custom_parameter('eventRequest', serialize($eventRequest));
            newrelic_notice_error($e->getMessage());
        }
        return $this;
    }

    public function handleResponse(Request $request, $responseCode = 200, array $responseBody = array())
    {
        // Set the response skeleton
        $response = new Response('', $responseCode);

        switch ($request->getMethod()) {
            case 'GET':
                if ($request->query->has('callback')) {
                    // JSONP
                    extract($this->buildJsonpResponse($responseBody, $request->query->get('callback')));
                } else {
                    // Send image beacon
                    extract($this->buildImageResponse());
                }
                break;
            case 'POST':
                // Send JSON response for POST requests
                extract($this->buildJsonResponse($responseBody));
                break;
            case 'OPTIONS':
                // Send CORS response
                extract($this->buildCorsResponse());
                $response->setPublic();
                $response->setSharedMaxAge(60*60*24*30);
                break;
            default:
                // Send image beacon
                extract($this->buildImageResponse());
                break;
        }

        $response->headers->add($headers);
        $response->setContent($content);
        return $response;
    }



    public function buildJsonpResponse(array $responseBody, $callback)
    {
        $content = $callback . '(' . @json_encode($responseBody) . ')';
        return array(
            'content'   => $content,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Content-Length'=> strlen($content),
                'Expires'       => 'Sat, 01 Jan 2000 01:01:01 GMT',
                'Cache-Control' => 'private, no-cache, no-cache=Set-Cookie, max-age=0, s-maxage=0',
                'Pragma'        => 'no-cache',
            ),
        );
    }

    public function buildJsonResponse(array $responseBody)
    {
        $content = @json_encode($responseBody);
        return array(
            'content'   => $content,
            'headers'   => array(
                'Content-Type'                  => 'application/json',
                'Content-Length'                => strlen($content),
                'Access-Control-Allow-Origin'   => '*',
                'Expires'       => 'Sat, 01 Jan 2000 01:01:01 GMT',
                'Cache-Control' => 'private, no-cache, no-cache=Set-Cookie, max-age=0, s-maxage=0',
                'Pragma'        => 'no-cache',
            ),
        );
    }

    public function buildCorsResponse()
    {
        // @todo Ensure the request origin header matches a known domain
        return array(
            'content'   => '',
            'headers'   => array(
                'Access-Control-Allow-Origin'   => '*',
                'Access-Control-Allow-Methods'  => $this->getAccessControlAllowMethods(),
                'Access-Control-Allow-Headers'  => $this->getAccessControlAllowHeaders(),
                'Access-Control-Max-Age'        => 60*60*24 // One day, in seconds
            ),
        );
    }

    public function getAccessControlAllowMethods()
    {
        return 'POST, OPTIONS';
    }

    public function getAccessControlAllowHeaders()
    {
        return 'origin, content-type, accept, user-agent';
    }

    public function buildImageResponse()
    {
        $content = sprintf(
            '%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%',
            71, 73,70,56,57,97,1,0,1,0,128,255,0,192,192,192,0,0,0,33,249,4,1,0,0,0,0,44,0,0,0,0,1,0,1,0,0,2,2,68,1,0,59
        );
        return array(
            'content'   => $content,
            'headers'   => array(
                'Content-Type'      => 'image/gif',
                'Content-Length'    => strlen($content),
                'Expires'       => 'Sat, 01 Jan 2000 01:01:01 GMT',
                'Cache-Control' => 'private, no-cache, no-cache=Set-Cookie, max-age=0, s-maxage=0',
                'Pragma'        => 'no-cache',
            ),
        );
    }
}
