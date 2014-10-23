<?php

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Debug\Debug;

if (extension_loaded('newrelic')) {
    newrelic_set_appname('Olytics');
}

// Get the environment from Apache
$env = strtolower(getenv('APP_ENV'));

// Determine the loader. All non-dev environments should use the bootstrap cache class
$loader = ($env !== 'dev') ? require_once __DIR__.'/../app/bootstrap.php.cache' : require_once __DIR__.'/../app/autoload.php';

// Load APC for improved autoloading performance based on server enviroment varible 'USE_APC'
if (getenv('USE_APC') === 'true') {
    // Uses the platform Kernel app name as a prefix in order to prevent cache key conflicts
    $apcLoader = new ApcClassLoader('cygnus_olytics_v1', $loader);
    $loader->unregister();
    $apcLoader->register(true);
}

// Create the request
$request = Request::createFromGlobals();
Request::setTrustedProxies(array('192.168.0.1/22'));

// Allow debug mode to be enabled independantly of environment.
$debug = false;
if ($request->cookies->has('debug') || 'dev' === $env) {
    $debug = true;
    Debug::enable();
}

require_once __DIR__.'/../app/AppKernel.php';

// Load environment based on server environment variable 'APP_ENV'
if ('prod' === $env) {
    $kernel = new AppKernel('prod', $debug);
    $kernel->loadClassCache();
} elseif ('dev' === $env) {
    $kernel = new AppKernel('dev', $debug);
} else {
    $kernel = new AppKernel('test', $debug);
    $kernel->loadClassCache();
}

// Load AppCache based on server environment variable 'APP_CACHE'
if (getenv('USE_CACHE') === 'true') {
    require_once __DIR__.'/../app/AppCache.php';
    $kernel = new AppCache($kernel);
    // When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
    Request::enableHttpMethodParameterOverride();
}

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
