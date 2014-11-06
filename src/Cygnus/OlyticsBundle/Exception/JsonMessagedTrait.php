<?php

namespace Cygnus\OlyticsBundle\Exception;

trait JsonMessagedTrait
{
    /**
     * The HTTP response code
     *
     * @var int
     */
    protected $responseCode = 500;

    /**
     * The response body
     *
     * @var array
     */
    protected $responseBody = [];

    /**
     * Sets the HTTP response code
     *
     * @param  int $code
     * @return self
     */
    public function setResponseCode($code)
    {
        $this->responseCode = (Integer) $code;
        return $this;
    }

    /**
     * Gets the HTTP response code
     *
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Sets the HTTP JSON response body
     *
     * @param  array $body
     * @return self
     */
    public function setResponseBody(array $body)
    {
        $this->responseBody = $body;
        return $this;
    }

    /**
     * Gets the HTTP response body (for use with JSON)
     *
     * @return array
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }
}
