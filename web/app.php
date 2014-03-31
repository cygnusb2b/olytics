<?php

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Debug\Debug;

$loader = require_once __DIR__.'/../app/bootstrap.php.cache';

// Use APC for autoloading to improve performance.
// Change 'sf2' to a unique prefix in order to prevent cache key conflicts
// with other applications also using APC.
/*
$apcLoader = new ApcClassLoader('sf2', $loader);
$loader->unregister();
$apcLoader->register(true);
*/

$request = Request::createFromGlobals();
Request::setTrustedProxies(array('192.168.0.1/22'));

$parts = explode('.', $request->server->get('SERVER_NAME'));

$env = 'prod';

if (in_array('dev', $parts)) {
    $env = 'dev';
}

require_once __DIR__.'/../app/AppKernel.php';
//require_once __DIR__.'/../app/AppCache.php';

switch ($env) {
    case 'dev':
        Debug::enable();
        $kernel = new AppKernel('dev', true);
        break;

    default:
        $kernel = new AppKernel('prod', false);
        break;
}

$kernel->loadClassCache();
//$kernel = new AppCache($kernel);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
