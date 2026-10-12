<?php
declare(strict_types=1);

namespace InfinityBlog\Storage;

interface StorageClientInterface
{
    /**
     * Upload an object to a bucket.
     *
     * @param string $bucket Bucket name
     * @param string $path Object path / key inside bucket
     * @param string $binaryData File contents
     * @param string $mimeType MIME type
     * @param bool $upsert Whether to overwrite if already exists
     * @return string The stored object key/path
     * @throws StorageException On upload failure
     */
    public function upload(string $bucket, string $path, string $binaryData, string $mimeType, bool $upsert = true): string;

    /**
     * Delete an object from a bucket.
     *
     * @param string $bucket Bucket name
     * @param string $path Object path / key inside bucket
     * @return bool True if deleted or already gone
     * @throws StorageException On delete failure
     */
    public function delete(string $bucket, string $path): bool;

    /**
     * Get the public URL for an object in a public bucket.
     *
     * @param string $bucket Bucket name
     * @param string $path Object path / key inside bucket
     * @return string Full public URL
     */
    public function getPublicUrl(string $bucket, string $path): string;

    /**
     * Create a short-lived signed URL for an object in a private bucket.
     *
     * @param string $bucket Bucket name
     * @param string $path Object path / key inside bucket
     * @param int $expiresInSeconds Duration in seconds until expiration
     * @return string Signed URL for accessing the private object
     * @throws StorageException On signing failure
     */
    public function createSignedUrl(string $bucket, string $path, int $expiresInSeconds = 3600): string;

    /**
     * Download an object's binary content.
     *
     * @param string $bucket Bucket name
     * @param string $path Object path / key inside bucket
     * @return string|null Binary content or null if not found
     * @throws StorageException On download failure
     */
    public function download(string $bucket, string $path): ?string;
}
