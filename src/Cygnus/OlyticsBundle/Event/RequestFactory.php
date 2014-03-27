<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\DataFormatter\FormatterInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as KernalRequest;

abstract class RequestFactory implements RequestFactoryInterface
{

    /**
     * The formatter to use when handling event request data
     *
     * @var Cygnus\OlyticsBundle\DataFormatter\FormatterInterface
     */
    protected $dataFormatter;

    /**
     * Constructor. Injects a data formatter instance
     *
     * @param  Cygnus\OlyticsBundle\DataFormatter\FormatterInterface $dataFormatter
     * @return void
     */
    public function __construct(FormatterInterface $dataFormatter)
    {
        $this->dataFormatter = $dataFormatter;
    }

    /**
     * Creates a new EventRequest from a kernel Request object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @param  string $vertical The vertical
     * @param  string $product  The product
     * @return mixed The newly created EventRequst
     */
    public function createFromRequest(KernalRequest $request, $vertical, $product)
    {
        $requestData = $this->getDataFromRequest($request);
        $requestData = $this->format($requestData);
        return $this->create($requestData, $vertical, $product);
    }

    /**
     * Creates a new EventRequest from an array
     *
     * @param  array $data
     * @param  string $vertical The vertical
     * @param  string $product  The product
     * @return mixed The newly created EventRequst
     */
    public function createFromArray(array $data, $vertical, $product)
    {
        $requestData = $this->format($data);
        return $this->create($requestData, $vertical, $product);
    }

    /**
     * Formats the request data
     *
     * @param  array $requestData
     * @return array The formatted data
     */
    protected function format(array $requestData) {
        return $this->dataFormatter->formatFromArray($requestData);
    }

    /**
     * Gets the request data from a Request object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @param  array The request data
     * @return void
     */
    protected function getDataFromRequest(KernalRequest $request)
    {
        switch ($request->getMethod()) {
            case 'POST':
                $requestData = $this->getRequestDataFromPost($request);
                break;
            case 'GET':
                $requestData = $this->getRequestDataFromGet($request);
                break;
            default:
                $requestData = array();
                break;
        }
        return $requestData;
    }

    /**
     * Creates a new EventRequest from a ParameterBag of data
     *
     * @param  ParameterBag $requestData
     * @param  string $vertical The vertical
     * @param  string $product  The product
     * @return mixed The newly created EventRequst
     */
    abstract public function create(ParameterBag $requestData, $vertical, $product);

    /**
     * Returns a value as an empty array if it isn't an array
     *
     * @param  mixed $value
     * @return array
     */
    public function asArray($value) {
        if (!is_array($value)) return array();
        return $value;
    }

    /**
     * Gets data from POST
     * Will treat as JSON if the Content-Type is application/json, text/plain or null
     * This allows support for XDomainRequest in IE8/9
     * Otherwise it will treats as a common post (application/x-www-form-urlencoded)
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return array The request data
     */
    protected function getRequestDataFromPost(KernalRequest $request)
    {
        $requestData = array();
        $contentType = $request->getContentType();
        if (in_array($contentType, ['json', 'txt']) || is_null($request->headers->get('Content-Type'))) {
            // Treat as a POST BODY JSON request
            $decoded = @json_decode($request->getContent(), true);
            if (is_array($decoded)) $requestData = $decoded;
        } else {
            // Treat as regular post
            $requestData = $request->request->all();
        }
        return $requestData;
    }

    /**
     * Gets data from GET
     * If the query string contains the 'enc' key, this will assume a base64 encoded JSON string
     * Otherwise, it will simply read the query string as-is
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return array The request data
     */
    protected function getRequestDataFromGet(KernalRequest $request)
    {
        $requestData = array();
        if ($request->query->has('enc')) {
            // Base64 and JSON decode
            $decoded = @json_decode(@base64_decode($request->query->get('enc')), true);
            if (is_array($decoded)) $requestData = $decoded;
        } else {
            $requestData = $request->query->all();
        }
        return $requestData;
    }
}
