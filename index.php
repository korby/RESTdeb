<?php

require_once __DIR__.'/config.php'; 
require_once __DIR__.'/silex.phar'; 

$app = new Silex\Application(); 

$app->get('/', function() { 
    return $GLOBALS['title']; 
}); 

use Symfony\Component\HttpFoundation\Response;
$app->post('/', function() use ($app) { 
    $request = $app['request'];
 
    // get the deb binary
    $debbin = $request->getContent();
    if (!$debbin) {
        return new Response("Input data is empty", 400);
    }
    file_put_contents('/tmp/debbin', $debbin);
    $output = array();
    $command1 = 'dpkg-deb -f /tmp/debbin 2>&1';
    $o = exec($command1, $output,  $ret1);
    if ($ret1 != 0) {
        return new Response(implode("\n", $output), 415);
    }

    // build debian deb filename
    $package = exec('dpkg-deb -f /tmp/debbin package', $output,  $ret);
    $version = exec('dpkg-deb -f /tmp/debbin version', $output,  $ret);
    $archi   = exec('dpkg-deb -f /tmp/debbin architecture', $output,  $ret);
    $name    = $package.'-'.$version.'_'.$archi.'.deb';

    // create the package
    $debpath = '/var/www/debian/'.$name;
    $ret = file_put_contents($debpath, $debbin);
    if ($ret === FALSE) {
        return new Response('Unable to write on '.$debpath, 507);
    }

    // reindex the debian repository
    $output = array();
    $command2  = '/usr/bin/dpkg-scanpackages /var/www/debian > /var/www/debian/Packages';
    $o = exec($command2, $output,  $ret);
    if ($ret != 0) {
        return new Response(implode("\n", $output), 500);
    }

    $command31 = 'rm -f /var/www/debian/Packages.gz';
    $o = exec($command31, $output, $ret);
    $command32 = '/bin/gzip /var/www/debian/Packages';
    $output = array();
    $o = exec($command32, $output, $ret);
    if ($ret != 0) {
        return new Response(implode("\n", $output), 500);
    }

    $r = new Response("Debian package $name added successfully", 201);
    $r->headers->set('Location', '/debian/'.$name);
    return $r;
}); 

$app->run(); 