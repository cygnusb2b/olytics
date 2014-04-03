<?php
namespace Cygnus\ApiSuiteBundle\RemoteKernel\Curl\Processor;

class ResponseBodyProcessor implements ProcessorInterface
{

    private $content = '';

    /**
     * Processes a cURL response body and sets the content
     *
     * @return int The length of the response body
     */
    public function process()
    {
        list($handle, $content) = func_get_args();

        $this->content .= $content;

        return strlen($content);
    }

    /**
     * Gets the body content
     *
     * @return string The processed body content
     */
    public function get()
    {
        return $this->content;
    }
}