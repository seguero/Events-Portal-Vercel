<?php

namespace framework;

/**
 * BlobStorage
 *
 * Generates short-lived client upload tokens for Vercel Blob and validates
 * the public Blob URLs returned by direct browser uploads.
 */
class BlobStorage
{
    // ADDED: keep the application rule that event images are at most 2 MB.
    private const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

    // ADDED: only these image types are accepted for event uploads.
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Create a short-lived Vercel Blob client token for one exact image path.
     *
     * The long-lived read/write token never leaves the PHP server. The browser
     * receives only a tightly scoped client token that can upload one image.
     */
    public function createClientUploadToken(string $contentType): array
    {
        // ADDED: validate MIME type before issuing any upload permission.
        if (!isset(self::ALLOWED_IMAGE_TYPES[$contentType])) {
            throw new \RuntimeException(
                'Unsupported image type. Only JPG, PNG and WEBP are allowed.'
            );
        }

        // ADDED: Vercel's client-token signing flow requires a read/write token.
        // Keep this value server-side in Vercel Environment Variables only.
        $readWriteToken = trim((string) getenv('BLOB_READ_WRITE_TOKEN'));

        if ($readWriteToken === '') {
            throw new \RuntimeException(
                'BLOB_READ_WRITE_TOKEN is not configured.'
            );
        }

        $storeId = $this->storeIdFromReadWriteToken($readWriteToken);
        $extension = self::ALLOWED_IMAGE_TYPES[$contentType];

        // ADDED: generate the destination on the server so the client cannot
        // request arbitrary Blob paths.
        $pathname = 'events/event_' . bin2hex(random_bytes(12)) . '.' . $extension;

        // ADDED: constrain the token to one exact path, one MIME type and 2 MB.
        // validUntil is milliseconds since Unix epoch, matching Vercel Blob.
        $payload = [
            'pathname' => $pathname,
            'allowedContentTypes' => [$contentType],
            'maximumSizeInBytes' => self::MAX_IMAGE_SIZE,
            'validUntil' => (int) floor(microtime(true) * 1000) + (5 * 60 * 1000),
            'addRandomSuffix' => false,
            'allowOverwrite' => false,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Could not encode Blob token payload.');
        }

        // ADDED: this mirrors Vercel Blob's current client-token format:
        // HMAC-SHA256(base64(payload), BLOB_READ_WRITE_TOKEN).
        $encodedPayload = base64_encode($json);
        $signature = hash_hmac('sha256', $encodedPayload, $readWriteToken);
        $securedPayload = base64_encode($signature . '.' . $encodedPayload);

        return [
            'clientToken' => 'vercel_blob_client_' . $storeId . '_' . $securedPayload,
            'pathname' => $pathname,
            'storeId' => $storeId,
        ];
    }

    /**
     * Confirm that a URL returned by the browser belongs to this Blob store.
     */
    public function isValidPublicBlobUrl(string $url): bool
    {
        // ADDED: reject malformed or non-HTTPS URLs.
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($scheme !== 'https') {
            return false;
        }

        // ADDED: resolve the same store ID used to issue the upload token.
        $storeId = $this->getConfiguredStoreId();

        if ($storeId === null) {
            return false;
        }

        $expectedHost = strtolower(
            $storeId . '.public.blob.vercel-storage.com'
        );

        // ADDED: only URLs from this project's public Blob store are accepted.
        if ($host !== $expectedHost) {
            return false;
        }

        // ADDED: event images must remain inside the events/ namespace.
        if (!str_starts_with($path, '/events/')) {
            return false;
        }

        // ADDED: keep URL validation aligned with the accepted image formats.
        return (bool) preg_match('/\.(?:jpg|png|webp)$/i', $path);
    }

    /**
     * Extract the Blob store ID from a Vercel read/write token.
     */
    private function storeIdFromReadWriteToken(string $token): string
    {
        // ADDED: current token shape is vercel_blob_rw_<storeId>_<secret>.
        $parts = explode('_', $token);
        $storeId = $parts[3] ?? '';

        if ($storeId === '') {
            throw new \RuntimeException('BLOB_READ_WRITE_TOKEN is invalid.');
        }

        return $storeId;
    }

    /**
     * Resolve the store ID for validating a returned public Blob URL.
     */
    private function getConfiguredStoreId(): ?string
    {
        $readWriteToken = trim((string) getenv('BLOB_READ_WRITE_TOKEN'));

        if ($readWriteToken !== '') {
            try {
                return $this->storeIdFromReadWriteToken($readWriteToken);
            } catch (\RuntimeException) {
                return null;
            }
        }

        // ADDED: fallback is useful for validation when BLOB_STORE_ID exists.
        $storeId = trim((string) getenv('BLOB_STORE_ID'));

        if ($storeId === '') {
            return null;
        }

        // ADDED: Vercel may expose this value as store_<id>.
        if (str_starts_with($storeId, 'store_')) {
            $storeId = substr($storeId, strlen('store_'));
        }

        return $storeId !== '' ? $storeId : null;
    }
}
