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
     * Creates a new EventRequest from a Request object
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return void
     */
    public function createFromRequest(KernalRequest $request)
    {
        $requestData = $this->getDataFromRequest($request);
        $requestData = $this->format($requestData);
        return $this->create($requestData);
    }

    /**
     * Creates a new EventRequest from an array
     *
     * @param  array $data
     * @return void
     */
    public function createFromArray(array $data)
    {
        $requestData = $this->format($data);
        return $this->create($requestData);
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
     * Creates a new EventRequest from an array of request data
     *
     * @param  array $requestData
     * @return void
     */
    abstract public function create(ParameterBag $requestData);

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
     *
     * @param  Symfony\Component\HttpFoundation\Request $request
     * @return array The request data
     */
    protected function getRequestDataFromPost(KernalRequest $request)
    {
        $requestData = array();
        $contentType = $request->getContentType();
        if ($contentType == 'json') {
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
        }
        return $requestData;
    }
}
