<?php

namespace Cygnus\OlyticsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/test');

        $this->assertTrue($crawler->filter('html:contains("OK")')->count() > 0);
    }
}
