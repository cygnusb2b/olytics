<?php
namespace Cygnus\OlyticsBundle\Event;

use Symfony\Component\HttpFoundation\Request as KernalRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

interface RequestFactoryInterface
{
    public function create(ParameterBag $requestData, $product);
    public function createFromRequest(KernalRequest $request, $product);
    public function createFromArray(array $data, $product);
}
