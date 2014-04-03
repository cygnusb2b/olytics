<?php
namespace Cygnus\ApiSuiteBundle\RemoteKernel\Curl\Processor;

interface ProcessorInterface {
    function process();
    function get();
}