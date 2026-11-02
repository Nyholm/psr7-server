<?php

declare(strict_types=1);

namespace Tests\Nyholm\Psr7Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;

/**
 * Headers passed to fromArrays() must not be duplicated on the resulting request.
 */
class FromArraysHeadersTest extends TestCase
{
    /** @var ServerRequestCreator */
    private $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $psr17Factory = new Psr17Factory();
        $this->creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    /**
     * The PSR-7 ServerRequest already derives a Host header from the URI; adding the Host header
     * from $headers on top of it (withAddedHeader) would give every request a duplicated value.
     */
    public function testHostHeaderIsNotDuplicated()
    {
        $server = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com'];
        $request = $this->creator->fromArrays($server, ServerRequestCreator::getHeadersFromServer($server));

        $this->assertSame(['example.com'], $request->getHeader('Host'));
        $this->assertSame('example.com', $request->getHeaderLine('Host'));
    }

    /**
     * Servers that set both CONTENT_TYPE and HTTP_CONTENT_TYPE (e.g. php -S) do not produce a
     * duplicated Content-Type header.
     */
    public function testContentTypeIsNotDuplicated()
    {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ];
        $request = $this->creator->fromArrays($server, ServerRequestCreator::getHeadersFromServer($server));

        $this->assertSame(['application/json'], $request->getHeader('Content-Type'));
    }
}
