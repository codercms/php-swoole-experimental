<?php

declare(strict_types=1);

namespace App\Http\Transformers;

use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SwooleResponseTransformer
{
    public const CHUNK_SIZE = 8192;

    /**
     * Send HTTP headers and content.
     *
     * @param SwooleResponse $swooleResponse
     * @param SymfonyResponse $symfonyResponse
     */
    public static function send(SwooleResponse $swooleResponse, SymfonyResponse $symfonyResponse): void
    {
        static::sendHeaders($swooleResponse, $symfonyResponse);
        static::sendContent($swooleResponse, $symfonyResponse);
    }

    /**
     * Send HTTP headers.
     *
     * @param SwooleResponse $swooleResponse
     * @param SymfonyResponse $symfonyResponse
     */
    protected static function sendHeaders(SwooleResponse $swooleResponse, SymfonyResponse $symfonyResponse): void
    {
        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (!$symfonyResponse->headers->has('Date')) {
            $symfonyResponse->setDate(\DateTime::createFromFormat('U', time()));
        }

        // headers
        // allPreserveCaseWithoutCookies() doesn't exist before Laravel 5.3
        $headers = $symfonyResponse->headers->allPreserveCase();
        if (isset($headers['Set-Cookie'])) {
            unset($headers['Set-Cookie']);
        }
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value);
            }
        }

        // status
        $swooleResponse->status($symfonyResponse->getStatusCode());

        // cookies
        // $cookie->isRaw() is supported after symfony/http-foundation 3.1
        // and Laravel 5.3, so we can add it back now
        foreach ($symfonyResponse->headers->getCookies() as $cookie) {
            $method = $cookie->isRaw() ? 'rawcookie' : 'cookie';
            $swooleResponse->$method(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
    }

    /**
     * Send HTTP content.
     * @param SwooleResponse $swooleResponse
     * @param SymfonyResponse $symfonyResponse
     */
    protected static function sendContent(SwooleResponse $swooleResponse, SymfonyResponse $symfonyResponse): void
    {
        if ($symfonyResponse instanceof StreamedResponse && property_exists($symfonyResponse, 'output')) {
            // TODO Add Streamed Response with output
            $swooleResponse->end($symfonyResponse->output);
        } elseif ($symfonyResponse instanceof BinaryFileResponse) {
            $swooleResponse->sendfile($symfonyResponse->getFile()->getPathname());
        } else {
            static::sendInChunk($swooleResponse, $symfonyResponse->getContent());
        }
    }

    /**
     * Send content in chunk
     *
     * @param SwooleResponse $response
     * @param string $content
     */
    protected static function sendInChunk(SwooleResponse $response, $content): void
    {
        if (strlen($content) <= static::CHUNK_SIZE) {
            $response->end($content);
            return;
        }

        foreach (str_split($content, static::CHUNK_SIZE) as $chunk) {
            $response->write($chunk);
        }

        $response->end();
    }
}
