<?php

declare(strict_types=1);

namespace Tests\Nyholm\Psr7Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Normalisation of $_FILES-style arrays into UploadedFileInterface instances.
 */
class UploadedFileNormalizationTest extends TestCase
{
    /** @var ServerRequestCreator */
    private $creator;

    /** @var string */
    private $tmpFile;

    protected function setUp(): void
    {
        parent::setUp();
        $psr17Factory = new Psr17Factory();
        $this->creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $this->tmpFile = \tempnam(\sys_get_temp_dir(), 'psr7s');
        \file_put_contents($this->tmpFile, 'uploaded-content');
    }

    protected function tearDown(): void
    {
        @\unlink($this->tmpFile);
        parent::tearDown();
    }

    private function files(array $files): array
    {
        return $this->creator->fromArrays(['REQUEST_METHOD' => 'POST'], [], [], [], [], $files)->getUploadedFiles();
    }

    /**
     * A numeric-string error code ("0", as produced by any array that went through a string
     * serialisation, and as used in the fixtures of ServerRequestCreatorTest::testNormalizeFiles)
     * must be treated as UPLOAD_ERR_OK, so the uploaded content is readable.
     */
    public function testStringErrorCodeZeroIsTreatedAsSuccess()
    {
        $file = $this->files(['f' => [
            'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $this->tmpFile, 'error' => '0', 'size' => '16',
        ]])['f'];

        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('uploaded-content', (string) $file->getStream());
    }

    /**
     * When tmp_name cannot be opened, the file must not be reported as UPLOAD_ERR_OK with an empty
     * stream; callers could not distinguish that from a successful (but empty) upload.
     */
    public function testUnreadableTmpFileIsNotReportedAsSuccessfulUpload()
    {
        $file = $this->files(['f' => [
            'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => \sys_get_temp_dir().'/does-not-exist-'.\uniqid(), 'error' => UPLOAD_ERR_OK, 'size' => 123,
        ]])['f'];

        $this->assertNotSame(UPLOAD_ERR_OK, $file->getError(), 'Unopenable tmp_name reported as a successful upload');
    }

    /**
     * A malformed nested spec (tmp_name is an array but "error" is a scalar) raises the documented
     * InvalidArgumentException instead of a PHP warning.
     */
    public function testMalformedNestedSpecRaisesInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->files(['f' => [
            'name' => ['x.txt'],
            'type' => ['text/plain'],
            'tmp_name' => [$this->tmpFile],
            'error' => 0,
            'size' => [16],
        ]]);
    }

    /**
     * Optional keys (size, type) that PSR-17 allows to be null may be missing from a nested spec
     * without "Undefined array key" warnings.
     */
    public function testNestedSpecWithMissingOptionalKeysDoesNotWarn()
    {
        $files = $this->files(['f' => [
            'name' => ['x.txt'],
            'tmp_name' => [$this->tmpFile],
            'error' => [UPLOAD_ERR_OK],
        ]]);

        $this->assertNull($files['f'][0]->getClientMediaType());
    }

    /**
     * A flat spec without an "error" key raises InvalidArgumentException.
     */
    public function testFlatSpecWithoutErrorKeyRaisesInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->files(['f' => ['tmp_name' => $this->tmpFile]]);
    }

    /**
     * By design: tmp_name is trusted; fromArrays() opens whatever path / stream wrapper
     * it is given (no is_uploaded_file() check, which would break long-running workers).
     * fromGlobals() is safe because PHP itself populates $_FILES. Callers of fromArrays() must never
     * pass attacker-controlled file specs.
     */
    public function testTmpNameIsTrustedByFromArrays()
    {
        $file = $this->files(['f' => [
            'name' => 'a.txt', 'type' => 'text/plain',
            'tmp_name' => 'php://filter/read=string.toupper/resource='.$this->tmpFile,
            'error' => UPLOAD_ERR_OK, 'size' => 16,
        ]])['f'];

        $this->assertSame('UPLOADED-CONTENT', (string) $file->getStream());
    }

    /** A non-OK error code never opens tmp_name. */
    public function testErrorCodeNotOkDoesNotOpenTmpName()
    {
        $psr17Factory = new Psr17Factory();
        $spy = new class($psr17Factory) implements StreamFactoryInterface {
            public $openedFiles = [];
            private $inner;

            public function __construct(StreamFactoryInterface $inner)
            {
                $this->inner = $inner;
            }

            public function createStream(string $content = ''): StreamInterface
            {
                return $this->inner->createStream($content);
            }

            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
            {
                $this->openedFiles[] = $filename;

                return $this->inner->createStreamFromFile($filename, $mode);
            }

            public function createStreamFromResource($resource): StreamInterface
            {
                return $this->inner->createStreamFromResource($resource);
            }
        };
        $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $spy);

        $file = $creator->fromArrays(['REQUEST_METHOD' => 'POST'], [], [], [], [], ['f' => [
            'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $this->tmpFile, 'error' => UPLOAD_ERR_PARTIAL, 'size' => 16,
        ]])->getUploadedFiles()['f'];

        $this->assertSame(UPLOAD_ERR_PARTIAL, $file->getError());
        $this->assertSame([], $spy->openedFiles);
    }

    /** Scalar entries are rejected with InvalidArgumentException. */
    public function testScalarEntryIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->files(['f' => 'not-a-spec']);
    }

    /** Nested (name[a][b]) specs are normalised to the same tree of UploadedFileInterface. */
    public function testDeeplyNestedSpecIsNormalised()
    {
        $files = $this->files(['f' => [
            'name' => ['a' => ['b' => 'x.txt']],
            'type' => ['a' => ['b' => 'text/plain']],
            'tmp_name' => ['a' => ['b' => $this->tmpFile]],
            'error' => ['a' => ['b' => UPLOAD_ERR_OK]],
            'size' => ['a' => ['b' => 16]],
        ]]);

        $this->assertInstanceOf(UploadedFileInterface::class, $files['f']['a']['b']);
        $this->assertSame('x.txt', $files['f']['a']['b']->getClientFilename());
    }
}
