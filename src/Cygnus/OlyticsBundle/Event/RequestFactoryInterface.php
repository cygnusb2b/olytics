<?php
namespace Cygnus\OlyticsBundle\Event;

use Symfony\Component\HttpFoundation\Request as KernalRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

interface RequestFactoryInterface
{
    public function create(ParameterBag $requestData);
    public function createFromRequest(KernalRequest $request);
    public function createFromArray(array $data);
}
