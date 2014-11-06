<?php

namespace Cygnus\OlyticsBundle\Exception;

interface JsonMessagedInterface
{
    public function setResponseCode($code);
    public function getResponseCode();
    public function setResponseBody(array $body);
    public function getResponseBody();
}
