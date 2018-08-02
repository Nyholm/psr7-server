<?php

declare(strict_types=1);

namespace Nyholm\Psr7Server;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

interface ServerRequestCreatorInterface
{
    /**
     * Create a new server request from the current environment variables.
     * Defaults to a GET request to minimise the risk of an \InvalidArgumentException.
     * Includes the current request headers as supplied by the server through `getallheaders()`.
     * If `getallheaders()` is unavailable on the current server it will fallback to its own `getHeadersFromServer()` method.
     *
     * @throws \InvalidArgumentException If no valid method or URI can be determined.
     */
    public function fromGlobals(): ServerRequestInterface;

    /**
     * Create a new server request from a set of arrays.
     *
     * @param array                                $server  Typically $_SERVER or similar structure.
     * @param array                                $headers Typically the output of getallheaders() or similar structure.
     * @param array                                $cookie  Typically $_COOKIE or similar structure.
     * @param array                                $get     Typically $_GET or similar structure.
     * @param array                                $post    Typically $_POST or similar structure.
     * @param array                                $files   Typically $_FILES or similar structure.
     * @param StreamInterface|resource|string|null $body    Typically stdIn
     *
     * @throws \InvalidArgumentException If no valid method or URI can be determined.
     */
    public function fromArrays(
        array $server,
        array $headers = [],
        array $cookie = [],
        array $get = [],
        array $post = [],
        array $files = [],
        $body = null
    ): ServerRequestInterface;

    /**
     * Get parsed headers from ($_SERVER) array.
     *
     * @param array $server Typically $_SERVER or similar structure.
     *
     * @return array
     */
    public static function getHeadersFromServer(array $server): array;
}
