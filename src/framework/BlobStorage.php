<?php

namespace framework;

/**
 * BlobStorage
 *
 * Vercel-only storage service for persistent public event images.
 */
class BlobStorage
{
    // ADDED: Current Vercel Blob API endpoint used by the official SDK.
    private const API_URL = 'https://vercel.com/api/blob/';

    // ADDED: Keep this in sync with Vercel Blob's current API version.
    private const API_VERSION = '12';

    // ADDED: Existing project rule: event images must stay below 2 MB.
    private const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

    /**
     * Upload an event image to a PUBLIC Vercel Blob store.
     *
     * @param array $file One entry from PHP's $_FILES array.
     * @return string Permanent public Blob URL to store in events.image_path.
     */
    public function uploadEventImage(array $file): string
    {
        // ADDED: Reject PHP-level upload failures before touching Blob storage.
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Image upload failed. Please try again.');
        }

        $tmpPath = $file['tmp_name'] ?? '';

        // ADDED: Confirm PHP actually created the temporary uploaded file.
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Image upload failed. Please try again.');
        }

        // ADDED: Preserve the assignment's existing 2 MB upload limit.
        if (($file['size'] ?? 0) > self::MAX_IMAGE_SIZE) {
            throw new \RuntimeException('Image must be smaller than 2MB.');
        }

        // ADDED: Detect MIME from file contents rather than trusting the browser.
        $mimeType = mime_content_type($tmpPath);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        // ADDED: Preserve the existing JPG / PNG / WEBP allow-list.
        if (!isset($allowedTypes[$mimeType])) {
            throw new \RuntimeException(
                'Please upload a valid image file: JPG, PNG or WEBP.'
            );
        }

        $extension = $allowedTypes[$mimeType];

        // ADDED: Use a server-generated pathname; never trust the user's filename.
        $pathname = 'events/event_' . bin2hex(random_bytes(10)) . '.' . $extension;

        // ADDED: Read the temporary upload while the current request is active.
        $contents = file_get_contents($tmpPath);

        if ($contents === false) {
            throw new \RuntimeException('Image upload failed. Please try again.');
        }

        return $this->putPublicBlob($pathname, $contents, $mimeType);
    }

    /**
     * Send raw bytes to Vercel Blob.
     */
    private function putPublicBlob(
        string $pathname,
        string $contents,
        string $contentType
    ): string {
        // ADDED: Prefer the read/write token when Vercel exposes it.
        $readWriteToken = trim((string) getenv('BLOB_READ_WRITE_TOKEN'));

        // ADDED: Newer Blob stores can use Vercel's short-lived OIDC token.
        $oidcToken = trim((string) getenv('VERCEL_OIDC_TOKEN'));
        $storeId = trim((string) getenv('BLOB_STORE_ID'));

        if ($readWriteToken !== '') {
            $token = $readWriteToken;

            // ADDED: Read/write tokens encode the store ID as:
            // vercel_blob_rw_<storeId>_<secret...>
            $parts = explode('_', $readWriteToken);
            $storeId = $parts[3] ?? $storeId;
        } elseif ($oidcToken !== '' && $storeId !== '') {
            $token = $oidcToken;

            // ADDED: Vercel may expose BLOB_STORE_ID as "store_<id>".
            if (str_starts_with($storeId, 'store_')) {
                $storeId = substr($storeId, strlen('store_'));
            }
        } else {
            error_log('Vercel Blob credentials are not configured.');

            throw new \RuntimeException(
                'Image upload failed. Please try again.'
            );
        }

        // ADDED: Match the request shape used by Vercel's official Blob SDK.
        $requestId = $storeId
            . ':'
            . (int) (microtime(true) * 1000)
            . ':'
            . bin2hex(random_bytes(6));

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/octet-stream',
            'Content-Length: ' . strlen($contents),
            'x-api-version: ' . self::API_VERSION,
            'x-api-blob-request-id: ' . $requestId,
            'x-api-blob-request-attempt: 0',
            'x-vercel-blob-store-id: ' . $storeId,
            'x-vercel-blob-access: public',
            'x-add-random-suffix: 1',
            'x-content-type: ' . $contentType,
        ];

        $url = self::API_URL . '?pathname=' . rawurlencode($pathname);

        // ADDED: PHP's HTTP stream keeps Dockerfile.vercel unchanged;
        // no cURL extension or Node.js runtime is required.
        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => implode("\r\n", $headers),
                'content' => $contents,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        // ADDED: Parse the HTTP status returned by Blob.
        $statusCode = 0;
        if (!empty($http_response_header[0])
            && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)
        ) {
            $statusCode = (int) $matches[1];
        }

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            // ADDED: Log diagnostics server-side without exposing credentials.
            error_log(
                'Vercel Blob upload failed. HTTP '
                . $statusCode
                . ' Response: '
                . ($response ?: 'no response body')
            );

            throw new \RuntimeException(
                'Image upload failed. Please try again.'
            );
        }

        $data = json_decode($response, true);

        // ADDED: Vercel Blob returns the permanent public URL in "url".
        if (!is_array($data) || empty($data['url'])) {
            error_log('Vercel Blob upload returned an invalid response.');

            throw new \RuntimeException(
                'Image upload failed. Please try again.'
            );
        }

        return (string) $data['url'];
    }
}
