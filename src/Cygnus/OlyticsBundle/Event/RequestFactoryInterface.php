<?php
namespace Cygnus\OlyticsBundle\Event;

use Symfony\Component\HttpFoundation\Request as KernalRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

interface RequestFactoryInterface
{
    public function create(ParameterBag $requestData, $vertical, $product);
    public function createFromRequest(KernalRequest $request, $vertical, $product);
    public function createFromArray(array $data, $vertical, $product);
}
