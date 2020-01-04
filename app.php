<?php

declare(strict_types=1);

require 'vendor/autoload.php';

$server = new \App\Http\SwooleServer();
$server->run();

