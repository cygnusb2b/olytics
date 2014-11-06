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

    public function testTrackPageView()
    {
        $client = static::createClient();

        $content = '{"session":{"id":"abfd1294-9d9c-4f3c-9e1a-7e20293a6dd2","createdAt":"Wed, 17 Sep 2014 14:47:26 GMT","visitorId":"49c5edb9-19dc-4c90-8f20-7de2d8b61604","customerId":null,"env":{"tz":300,"res":{"width":1440,"height":900},"windowRes":{"width":1440,"height":749}}},"event":{"action":"view","entity":{"type":"page","clientId":"$hash::http://dev.olytics.localhost/test","keyValues":{"url":"http://dev.olytics.localhost/test","title":"Home | Olytics","type":null},"relatedTo":[]},"data":{},"createdAt":"Wed, 17 Sep 2014 14:48:24 GMT","relatedEntities":[]},"appendCustomer":false}';

        $client->request('POST', '/events/test/test', [], [], ['CONTENT_TYPE' => 'application/json'], $content);

        $this->assertTrue($client->getResponse()->isSuccessful());
    }
}
