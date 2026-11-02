<?php

declare(strict_types=1);

namespace Tests\Nyholm\Psr7Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;

/**
 * Header marshalling from server variables (getHeadersFromServer / fromArrays / fromGlobals).
 */
class HeaderMarshallingTest extends TestCase
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
     * Header values that are PHP-falsy but not empty ("0") must be kept
     * (e.g. "Content-Length: 0", "Max-Forwards: 0", "DNT: 0").
     */
    public function testHeaderWithValueZeroIsKept()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([
            'HTTP_MAX_FORWARDS' => '0',
            'CONTENT_LENGTH' => '0',
        ]);

        $this->assertSame(['max-forwards' => '0', 'content-length' => '0'], $headers);
    }

    /**
     * getHeadersFromServer() is public; an array with an integer key (e.g. a list-shaped env array)
     * is skipped instead of raising a TypeError from strpos() under strict_types.
     */
    public function testIntegerKeysInServerArrayAreIgnored()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([0 => 'value', 'HTTP_X_A' => 'b']);

        $this->assertSame(['x-a' => 'b'], $headers);
    }

    /** Apache's REDIRECT_HTTP_AUTHORIZATION is used when HTTP_AUTHORIZATION is absent. */
    public function testRedirectAuthorizationIsMappedWhenOriginalMissing()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([
            'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc',
        ]);

        $this->assertSame(['authorization' => 'Bearer abc'], $headers);
    }

    /** when both are present the REDIRECT_ copy never overrides nor duplicates the original. */
    public function testRedirectAuthorizationDoesNotOverrideOrDuplicate()
    {
        foreach ([
            ['HTTP_AUTHORIZATION' => 'Bearer real', 'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer other'],
            ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer other', 'HTTP_AUTHORIZATION' => 'Bearer real'],
        ] as $server) {
            $headers = ServerRequestCreator::getHeadersFromServer($server);
            $request = $this->creator->fromArrays(['REQUEST_METHOD' => 'GET'] + $server, $headers);

            $this->assertSame(['Bearer real'], $request->getHeader('Authorization'));
        }
    }

    /**
     * A client cannot forge a REDIRECT_* server variable through a request header —
     * "Redirect-Http-Authorization: x" arrives as HTTP_REDIRECT_HTTP_AUTHORIZATION, which is mapped
     * to its own header name, not to Authorization. Double-prefixed keys are stripped only once.
     */
    public function testClientCannotForgeAuthorizationViaRedirectPrefix()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([
            'HTTP_REDIRECT_HTTP_AUTHORIZATION' => 'Bearer forged',
            'REDIRECT_REDIRECT_HTTP_AUTHORIZATION' => 'Bearer forged2',
        ]);

        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertSame(['redirect-http-authorization' => 'Bearer forged'], $headers);
    }

    /**
     * By design: PHP_AUTH_USER / PHP_AUTH_PW / PHP_AUTH_DIGEST are NOT turned into an
     * Authorization header, so they cannot be used to inject or duplicate credentials. Applications
     * on CGI/FastCGI that rely on them must read getServerParams().
     */
    public function testPhpAuthVariablesAreNotConvertedToAuthorizationHeader()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'secret',
            'PHP_AUTH_DIGEST' => 'username="admin"',
            'AUTH_TYPE' => 'Basic',
        ]);

        $this->assertSame([], $headers);
    }

    /** CONTENT_TYPE / CONTENT_LENGTH (no HTTP_ prefix in CGI) become headers. */
    public function testContentTypeAndLengthAreMapped()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '12',
        ]);

        $this->assertSame(['content-type' => 'application/json', 'content-length' => '12'], $headers);
    }

    /**
     * Non-header server variables (REMOTE_ADDR, SERVER_*, REQUEST_*, DOCUMENT_ROOT, ...)
     * are not exposed as headers.
     */
    public function testOnlyHttpAndContentPrefixedKeysBecomeHeaders()
    {
        $headers = ServerRequestCreator::getHeadersFromServer([
            'REMOTE_ADDR' => '10.0.0.1',
            'SERVER_NAME' => 'example.com',
            'DOCUMENT_ROOT' => '/var/www',
            'REQUEST_URI' => '/',
            'HTTPS' => 'on',
            'HTTP_X_TEST' => 'y',
        ]);

        $this->assertSame(['x-test' => 'y'], $headers);
    }

    /**
     * (Delegated to the PSR-7 implementation.) CR/LF in a header value is rejected by
     * nyholm/psr7, so response-splitting style values cannot enter the request object.
     */
    public function testHeaderValueWithCrlfIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->creator->fromArrays(['REQUEST_METHOD' => 'GET'], ['x-test' => "a\r\nSet-Cookie: x=y"]);
    }

    /**
     * fromGlobals() only exposes $_POST as parsed body for form content types,
     * matched case-insensitively and ignoring parameters.
     *
     * @backupGlobals enabled
     */
    public function testFromGlobalsOnlyUsesPostForFormContentTypes()
    {
        $_POST = ['a' => 'b'];
        $_GET = $_COOKIE = $_FILES = [];

        $_SERVER = ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'Multipart/Form-Data; boundary=x'];
        $this->assertSame(['a' => 'b'], $this->creator->fromGlobals()->getParsedBody());

        $_SERVER = ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'];
        $this->assertNull($this->creator->fromGlobals()->getParsedBody());
    }

    /**
     * By design: the request method is passed through verbatim (method names are case-sensitive,
     * RFC 9110 §9.1). A missing REQUEST_METHOD in fromArrays() is a hard error, not a silent GET.
     */
    public function testMissingRequestMethodThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->creator->fromArrays([]);
    }
}
