<?php

namespace Cygnus\OlyticsBundle\Detector;

interface DetectorInterface
{
    public function hasMatch($needle);
    public function doMatch($needle);
    public function getMatchData();
}
