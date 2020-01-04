<?php

declare(strict_types=1);

namespace App\Http\Transformers;

use Symfony\Component\HttpFoundation\ParameterBag;
use Swoole\Http\Request as SwooleRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class SwooleRequestTransformer
{
    public static function transform(SwooleRequest $request): SymfonyRequest
    {
        $parameters = self::toIlluminateParameters($request);

        return static::getRequest(...$parameters);
    }

    protected static function getRequest($get, $post, $cookie, $files, $server, $content): SymfonyRequest
    {
        if (array_key_exists('HTTP_CONTENT_LENGTH', $server)) {
            $server['CONTENT_LENGTH'] = $server['HTTP_CONTENT_LENGTH'];
        }
        if (array_key_exists('HTTP_CONTENT_TYPE', $server)) {
            $server['CONTENT_TYPE'] = $server['HTTP_CONTENT_TYPE'];
        }

        $request = new SymfonyRequest($get, $post, [], $cookie, $files, $server, $content);

        if (0 === strpos($request->headers->get('CONTENT_TYPE', ''), 'application/x-www-form-urlencoded')
            && in_array(strtoupper($request->server->get('REQUEST_METHOD', 'GET')), ['PUT', 'DELETE', 'PATCH'])
        ) {
            parse_str($request->getContent(), $data);
            $request->request = new ParameterBag($data);
        }

        return $request;
    }

    /**
     * Transforms request parameters.
     *
     * @param \Swoole\Http\Request $request
     *
     * @return array
     */
    protected static function toIlluminateParameters(\Swoole\Http\Request $request): array
    {
        $get = $request->get ?? [];
        $post = $request->post ?? [];
        $cookie = $request->cookie ?? [];
        $files = $request->files ?? [];
        $header = $request->header ?? [];
        $server = $request->server ?? [];
        $server = static::transformServerParameters($server, $header);
        $content = $request->rawContent();

        return [$get, $post, $cookie, $files, $server, $content];
    }

    /**
     * Transforms $_SERVER array.
     *
     * @param array $server
     * @param array $header
     *
     * @return array
     */
    protected static function transformServerParameters(array $server, array $header): array
    {
        $__SERVER = [];

        foreach ($server as $key => $value) {
            $key = strtoupper($key);
            $__SERVER[$key] = $value;
        }

        foreach ($header as $key => $value) {
            $key = str_replace('-', '_', $key);
            $key = strtoupper($key);

            if (! in_array($key, ['REMOTE_ADDR', 'SERVER_PORT', 'HTTPS'])) {
                $key = 'HTTP_' . $key;
            }

            $__SERVER[$key] = $value;
        }

        return $__SERVER;
    }
}
