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
     *
     * @throws \InvalidArgumentException if no valid method or URI can be determined
     */
    public function fromGlobals(): ServerRequestInterface;

    /**
     * Create a new server request from a set of arrays.
     *
     * @param array                                $server  typically $_SERVER or similar structure
     * @param array                                $headers typically the output of getallheaders() or similar structure
     * @param array                                $cookie  typically $_COOKIE or similar structure
     * @param array                                $get     typically $_GET or similar structure
     * @param array                                $post    typically $_POST or similar structure
     * @param array                                $files   typically $_FILES or similar structure
     * @param StreamInterface|resource|string|null $body    Typically stdIn
     *
     * @throws \InvalidArgumentException if no valid method or URI can be determined
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
     * @param array $server typically $_SERVER or similar structure
     *
     * @return array
     */
    public function getHeadersFromServer(array $server): array;
}
