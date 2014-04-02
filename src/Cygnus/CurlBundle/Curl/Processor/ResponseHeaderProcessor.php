<?php
namespace Cygnus\CurlBundle\Curl\Processor;

class ResponseHeaderProcessor implements ProcessorInterface
{

    private $headers = array();
    private $protocolVersion;
    private $statusCode;
    private $statusMessage;

    /**
     * Processes a cURL response header
     *
     * @return int The length of the response header
     */
    public function process()
    {
        list($handle, $header) = func_get_args();

        $trimmedHeader = trim($header);

        if (stripos($trimmedHeader, 'http/') !== false) {
            $this->parseResponseCode($trimmedHeader);
        } else {
            $this->parseHeader($trimmedHeader);
        }
        return strlen($header);
    }

    /**
     * Parses the HTTP response code from the header: HTTP/1.1 200 OK
     * Will set protocal version (e.g. 1.1), status code (e.g. 200), and message (e.g. OK)
     *
     * @return void
     */
    protected function parseResponseCode($header)
    {
        list($version, $code, $message) = explode(' ', $header);

        $versionParts = explode('/', $version);

        $this->protocolVersion = end($versionParts);
        $this->statusCode = (int) $code;
        $this->statusMessage = $message;
    }

    /**
     * Parses an individual header and sets it to the header array
     * 'Content-Type: application/json' is set to $this->headers['Content-Type'] = 'application/json'
     *
     * @return void
     */
    protected function parseHeader($header)
    {
        if (!empty($header)) {
            $pos = strpos($header, ": ");

            if (false !== $pos) {
                $name = substr($header, 0, $pos);
                $value = substr($header, $pos+2);

                $this->headers[$name] = $value;
            }
        }
    }

    /**
     * Gets the processed headers
     *
     * @return array
     */
    public function get()
    {
        return $this->headers;
    }

    /**
     * Gets the protocal version, e.g. 1.1
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * Gets the response status code, e.g. 200
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Gets the response status message, e.g. OK
     *
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->statusMessage;
    }
}
