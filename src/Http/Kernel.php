<?php

declare(strict_types=1);

namespace App\Http;

use App\Database\Database;
use App\Http\Controllers\ItemController;
use App\Http\Transformers\SwooleRequestTransformer;
use App\Http\Transformers\SwooleResponseTransformer;
use FastRoute\Dispatcher;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Kernel
{
    private Dispatcher $router;
    private Database $database;

    private array $controllers = [];

    private static Kernel $instance;

    public function __construct()
    {
        self::$instance = $this;

        $dsn = 'pgsql:host=127.0.0.1;port=5433;dbname=test;user=test;password=secret';
        $this->database = new Database($dsn);

        $this->controllers[ItemController::class] = new ItemController();

        $this->router = \FastRoute\simpleDispatcher(
            function (\FastRoute\RouteCollector $r) {
                $r->addRoute(
                    'GET',
                    '/verifier/items',
                    [
                        $this->controllers[ItemController::class],
                        'index',
                    ]
                );
            }
        );
    }

    public static function getKernel(): Kernel
    {
        return self::$instance;
    }

    public static function getDatabase(): Database
    {
        return self::$instance->database;
    }

    public function handleSwooleRequest(SwooleRequest $request, SwooleResponse $response): void
    {
        $symfonyRequest = SwooleRequestTransformer::transform($request);

        $routeInfo = $this->router->dispatch($symfonyRequest->getMethod(), $symfonyRequest->getRequestUri());

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                SwooleResponseTransformer::send($response, $this->getNotFoundResponse());
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                SwooleResponseTransformer::send($response, $this->getMethodNotAllowedResponse($allowedMethods));
                break;
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                try {
                    $symfonyResponse = $handler($symfonyRequest, $vars);
                } catch (\Throwable $e) {
                    $symfonyResponse = $this->getErrorResponse($e);
                }

                SwooleResponseTransformer::send($response, $symfonyResponse);
                break;
        }
    }

    private function getErrorResponse(\Throwable $e): JsonResponse
    {
        return new JsonResponse([
            'message' => 'server_error',
            'exMessage' => $e->getMessage(),
            'trace' => $e->getTrace(),
        ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function getNotFoundResponse(): JsonResponse
    {
        return new JsonResponse(['message' => 'not_found'], SymfonyResponse::HTTP_OK);
    }

    private function getMethodNotAllowedResponse(array $allowedMethods): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'method_not_allowed', 'allowed' => $allowedMethods],
            SymfonyResponse::HTTP_METHOD_NOT_ALLOWED
        );
    }
}
