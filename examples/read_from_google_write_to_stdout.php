<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Zaloopa\SelectEventLoop;



const NO_BLOCKING = false;
const BUFF_SIZE_1024 = 1024;


$stdout = STDOUT;
$ssb = stream_set_blocking($stdout, NO_BLOCKING);
if ($ssb === false) {
    exit("Can't set blocking STDOUT");
}


$clientStream = stream_socket_client('tcp://www.google.com:80');
if (!$clientStream) {
    exit("Fuck\n");
}

stream_set_blocking($clientStream, false);
fwrite($clientStream, "GET / HTTP/1.1\r\nHost: www.google.com\r\nConnection: close\r\n\r\n");

$selector = new SelectEventLoop();

$buffer = null;
$selector->registerReadStream($clientStream, static function ($stream) use (&$buffer, $selector) {
    $content = stream_get_contents($stream, BUFF_SIZE_1024);
    $buffer = $content;
    $selector->removeReadStream($stream);
});

$selector->registerWriteStream($stdout, static function ($stream) use (&$buffer) {

    if ($buffer !== null) {
        fwrite($stream, $buffer);
        $buffer = null;
    }

});

$selector->run();

fclose($stdin);
fclose($stdout);
fclose($clientStream);

