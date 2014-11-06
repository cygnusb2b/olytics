<?php

namespace Cygnus\OlyticsBundle\Exception;

trait JsonMessagedTrait
{
    protected $responseCode = 500;

    protected $responseBody = [];

    public function setResponseCode($code)
    {
        $this->responseCode = (Integer) $code;
        return $this;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    public function setResponseBody(array $body)
    {
        $this->responseBody = $body;
        return $this;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }
}
