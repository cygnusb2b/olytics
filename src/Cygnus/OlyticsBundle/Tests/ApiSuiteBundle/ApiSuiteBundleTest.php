<?php

namespace Cygnus\OlyticsBundle\Tests\ApiSuiteBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use Cygnus\ApiSuiteBundle\ApiClient\Omeda\ApiClientOmeda;

class ApiSuiteBundleTest extends WebTestCase
{

    /**
     * Ensures that the ApiClientOmeda class can be autoloaded
     */
    public function testApiClientOmedaContainsHttpKernel()
    {
        $apiClient = new ApiClientOmeda();
        $this->assertClassHasAttribute('httpKernel', '\Cygnus\ApiSuiteBundle\ApiClient\Omeda\ApiClientOmeda');
    }
}
