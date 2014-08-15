<?php
namespace Cygnus\OlyticsBundle\Event;

use Symfony\Component\HttpFoundation\Request as KernalRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

interface RequestFactoryInterface
{
    public function create(ParameterBag $requestData, $account, $product);
    public function createFromRequest(KernalRequest $request, $account, $product);
    public function createFromArray(array $data, $account, $product);
}
