<?php

namespace Cygnus\OlyticsBundle\Exception;

interface JsonMessagedInterface
{
    /**
     * Sets the HTTP response code
     *
     * @param  int $code
     * @return self
     */
    public function setResponseCode($code);

    /**
     * Gets the HTTP response code
     *
     * @return int
     */
    public function getResponseCode();

    /**
     * Sets the HTTP JSON response body
     *
     * @param  array $body
     * @return self
     */
    public function setResponseBody(array $body);

    /**
     * Gets the HTTP response body (for use with JSON)
     *
     * @return array
     */
    public function getResponseBody();
}
