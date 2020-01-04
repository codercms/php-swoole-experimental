<?php

declare(strict_types=1);

namespace App\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

class SwooleServer
{
    private \Swoole\Http\Server $server;
    private Kernel $kernel;

    public function __construct()
    {
        $this->server = new \Swoole\Http\Server(
            '127.0.0.1',
            9501
        );
        $this->server->set([
            'daemonize' => false,
            'send_yield' => true,
            'socket_type' => SWOOLE_SOCK_TCP,
            'process_type' => SWOOLE_PROCESS,
//            'worker_num' => 1,
//            'reactor_num' => 1,
        ]);

        $this->registerEvents();
    }

    private function registerEvents(): void
    {
        $this->server->on('request', \Closure::fromCallable([$this, 'onRequest']));
        $this->server->on('WorkerStart', \Closure::fromCallable([$this, 'onWorkerStart']));

        $this->server->on(
            'Start',
            function () {
                swoole_set_process_name('master process');

                echo "Server started" . PHP_EOL;
            }
        );

        $this->server->on(
            'ManagerStart',
            function () {
                swoole_set_process_name('manager process');
            }
        );
    }

    private function onWorkerStart()
    {
        swoole_set_process_name('worker process');

        if (\extension_loaded('opcache')) {
            opcache_reset();
        }

        $this->kernel = new Kernel();
    }

    private function onRequest(Request $request, Response $response)
    {
        $this->kernel->handleSwooleRequest($request, $response);
    }

    public function run()
    {
        $this->server->start();
    }
}
