<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Service\Ssl;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Service\Ssl\SslCertificateProvider;

final class SslCertificateProviderTest extends TestCase
{
    private SslCertificateProvider $provider;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->provider = new SslCertificateProvider();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // -------------------------------------------------------------------------
    // resolve() — CA certificate
    // -------------------------------------------------------------------------

    #[Test]
    public function resolveReturnsNullWhenBothParamsAreNull(): void
    {
        self::assertNull($this->provider->resolve(null, null));
    }

    #[Test]
    public function resolveReturnsLocalPathWhenFileExists(): void
    {
        $path = $this->createTempFile('ca-cert-content');

        self::assertSame($path, $this->provider->resolve(null, $path));
    }

    #[Test]
    public function resolveLocalPathTakesPriorityOverUrl(): void
    {
        $path = $this->createTempFile('ca-cert-content');

        // URL is provided but local path takes priority
        self::assertSame($path, $this->provider->resolve('https://example.com/ca.pem', $path));
    }

    #[Test]
    public function resolveThrowsRuntimeExceptionWhenLocalPathNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSL CA certificate file not found:');

        $this->provider->resolve(null, '/non/existent/path/ca.pem');
    }

    #[Test]
    public function resolveUsesExistingCacheForUrl(): void
    {
        $url = 'https://example.com/ca-cert-cached-test.pem';
        $cacheKey = hash('sha256', $url);
        $cachedPath = sys_get_temp_dir().'/rossel_kafka_ca_'.$cacheKey.'.pem';

        // Pre-create the cached file to bypass actual download
        file_put_contents($cachedPath, 'cached cert content');
        $this->tempFiles[] = $cachedPath;

        $result = $this->provider->resolve($url, null);

        self::assertSame($cachedPath, $result);
    }

    // -------------------------------------------------------------------------
    // resolvePem() — client certificate / key
    // -------------------------------------------------------------------------

    #[Test]
    public function resolvePemReturnsNullWhenParamIsNull(): void
    {
        self::assertNull($this->provider->resolvePem(null, 'client_cert'));
    }

    #[Test]
    public function resolvePemWritesPemContentToTempFile(): void
    {
        $pemContent = "-----BEGIN CERTIFICATE-----\nMIIBtest\n-----END CERTIFICATE-----\n";

        $result = $this->provider->resolvePem($pemContent, 'client_cert');
        if (null !== $result) {
            $this->tempFiles[] = $result;
        }

        self::assertNotNull($result);
        self::assertFileExists($result);
        self::assertStringContainsString('rossel_kafka_client_cert_', basename($result));
        self::assertSame($pemContent, file_get_contents($result));
    }

    #[Test]
    public function resolvePemCachesSamePemContentOnSecondCall(): void
    {
        $pemContent = "-----BEGIN CERTIFICATE-----\nMIIBcached\n-----END CERTIFICATE-----\n";

        $first = $this->provider->resolvePem($pemContent, 'client_cert');
        $second = $this->provider->resolvePem($pemContent, 'client_cert');

        if (null !== $first) {
            $this->tempFiles[] = $first;
        }

        self::assertSame($first, $second, 'Same PEM content must resolve to the same temp file path.');
    }

    #[Test]
    public function resolvePemReturnsDifferentPathsForDifferentContents(): void
    {
        $pem1 = "-----BEGIN CERTIFICATE-----\nAAA\n-----END CERTIFICATE-----\n";
        $pem2 = "-----BEGIN CERTIFICATE-----\nBBB\n-----END CERTIFICATE-----\n";

        $path1 = $this->provider->resolvePem($pem1, 'cert');
        $path2 = $this->provider->resolvePem($pem2, 'cert');

        if (null !== $path1) {
            $this->tempFiles[] = $path1;
        }
        if (null !== $path2) {
            $this->tempFiles[] = $path2;
        }

        self::assertNotSame($path1, $path2);
    }

    #[Test]
    public function resolvePemReturnsExistingFilePathWhenNotPemContent(): void
    {
        $path = $this->createTempFile('not pem content');

        self::assertSame($path, $this->provider->resolvePem($path, 'client_cert'));
    }

    #[Test]
    public function resolvePemThrowsWhenFilePathDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSL certificate file not found:');

        $this->provider->resolvePem('/non/existent/client.pem', 'client_cert');
    }

    #[Test]
    public function resolvePemHandlesPemWithLeadingWhitespace(): void
    {
        $pemContent = "   \n-----BEGIN PRIVATE KEY-----\nMIItest\n-----END PRIVATE KEY-----\n";

        $result = $this->provider->resolvePem($pemContent, 'client_key');
        if (null !== $result) {
            $this->tempFiles[] = $result;
        }

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pemPrefixProvider(): iterable
    {
        yield 'CERTIFICATE' => ["-----BEGIN CERTIFICATE-----\ndata\n-----END CERTIFICATE-----\n"];
        yield 'PRIVATE KEY' => ["-----BEGIN PRIVATE KEY-----\ndata\n-----END PRIVATE KEY-----\n"];
        yield 'RSA PRIVATE KEY' => ["-----BEGIN RSA PRIVATE KEY-----\ndata\n-----END RSA PRIVATE KEY-----\n"];
        yield 'CERTIFICATE REQUEST' => ["-----BEGIN CERTIFICATE REQUEST-----\ndata\n-----END CERTIFICATE REQUEST-----\n"];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('pemPrefixProvider')]
    public function resolvePemRecognizesVariousPemFormats(string $pemContent): void
    {
        $result = $this->provider->resolvePem($pemContent, 'cert');
        if (null !== $result) {
            $this->tempFiles[] = $result;
        }

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rossel_test_');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}
