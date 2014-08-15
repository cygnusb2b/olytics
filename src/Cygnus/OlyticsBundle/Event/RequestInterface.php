<?php
namespace Cygnus\OlyticsBundle\Event;

interface RequestInterface
{
    public function getEvent();
    public function getAccount();
    public function getProduct();
}
