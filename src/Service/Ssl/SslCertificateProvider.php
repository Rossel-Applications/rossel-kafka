<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Ssl;

final class SslCertificateProvider
{
    /**
     * Resolve the CA certificate path from either a local file path or a remote URL.
     *
     * If a local path is provided, it is used directly.
     * If a URL is provided, the certificate is downloaded and cached in the system temp directory.
     * Returns null when neither is provided (no SSL).
     *
     * @throws \RuntimeException when the certificate cannot be downloaded or local file is missing
     */
    public function resolve(
        ?string $url,
        ?string $localPath,
    ): ?string {
        if (null !== $localPath) {
            if (!file_exists($localPath)) {
                throw new \RuntimeException(\sprintf('SSL CA certificate file not found: %s', $localPath));
            }

            return $localPath;
        }

        if (null !== $url) {
            return $this->downloadAndCache($url);
        }

        return null;
    }

    /**
     * Resolve a client certificate or private key from either PEM content or a local file path.
     *
     * If the value starts with a PEM header, it is written to a temp file and cached.
     * Otherwise, the value is treated as a file path.
     * Returns null when no value is provided.
     *
     * @throws \RuntimeException when the file path does not exist or the temp file cannot be written
     */
    public function resolvePem(?string $pemOrPath, string $tempPrefix): ?string
    {
        if (null === $pemOrPath) {
            return null;
        }

        if (str_starts_with(ltrim($pemOrPath), '-----BEGIN')) {
            $cacheKey = hash('sha256', $pemOrPath);
            $tmpPath = sys_get_temp_dir().'/rossel_kafka_'.$tempPrefix.'_'.$cacheKey.'.pem';

            if (!file_exists($tmpPath) && false === file_put_contents($tmpPath, $pemOrPath)) {
                throw new \RuntimeException(\sprintf('Failed to write PEM content to: %s', $tmpPath));
            }

            return $tmpPath;
        }

        if (!file_exists($pemOrPath)) {
            throw new \RuntimeException(\sprintf('SSL certificate file not found: %s', $pemOrPath));
        }

        return $pemOrPath;
    }

    private function downloadAndCache(
        string $url,
    ): string {
        $cacheKey = hash('sha256', $url);
        $cachedPath = sys_get_temp_dir().'/rossel_kafka_ca_'.$cacheKey.'.pem';

        if (file_exists($cachedPath)) {
            return $cachedPath;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => true,
                'ignore_errors' => false,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        if (!file_exists($url)
            || false === \in_array($content = file_get_contents($url, false, $context), ['', false], true)
        ) {
            throw new \RuntimeException(\sprintf('Failed to download SSL CA certificate from URL: %s', $url));
        }

        if (false === file_put_contents($cachedPath, $content)) {
            throw new \RuntimeException(\sprintf('Failed to write SSL CA certificate to: %s', $cachedPath));
        }

        return $cachedPath;
    }
}
