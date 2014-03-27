<?php
namespace Cygnus\OlyticsBundle\Event;

use Symfony\Component\HttpFoundation\ParameterBag;

class Request implements RequestInterface
{
    protected $event;

    protected $vertical;

    protected $product;

    public function getEvent()
    {
        return $this->event;
    }

    public function getVertical()
    {
        return $this->vertical;
    }

    public function getProduct()
    {
        return $this->product;
    }
}
