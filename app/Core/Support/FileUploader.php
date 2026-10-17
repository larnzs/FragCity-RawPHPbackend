<?php

namespace Flute\Core\Support;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use WebPConvert\WebPConvert;
use ZipArchive;

class FileUploader
{
    private const DEFAULT_ZIP_MAX_ENTRIES = 5000;

    private const DEFAULT_ZIP_MAX_TOTAL_SIZE = 250 * 1024 * 1024;

    private const DEFAULT_ZIP_MIN_COMPRESSION_RATIO = 0.001;

    private $targetDirectory;

    private $filesystem;

    private $logger;

    private const DANGEROUS_EXTENSIONS = [
        'php',
        'phtml',
        'php3',
        'php4',
        'php5',
        'phps',
        'phar',
        'exe',
        'sh',
        'bat',
        'cmd',
        'com',
        'scr',
        'vbs',
        'jsp',
        'asp',
        'aspx',
        'cgi',
        'pl',
        'py',
        'shtml',
        'svg',
        'htaccess',
        'htpasswd',
    ];

    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger,
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->targetDirectory = 'public/assets/uploads';
    }

    /**
     * Uploads an image with security checks and optional conversion to WebP.
     *
     * @param int $maxSize Maximum file size in megabytes
     * @return string|null Path to the uploaded file or null in case of an error
     */
    public function uploadImage(UploadedFile $file, int $maxSize): ?string
    {
        $safeFilename = bin2hex(random_bytes(16));
        $extension = $file->guessExtension();

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $this->validateOriginalName($file->getClientOriginalName());
        $this->validateFileSize($file, $maxSize);

        $mimeType = $file->getMimeType();

        if (
            $extension === null
            || !in_array($mimeType, $allowedMimeTypes, true)
            || !in_array($extension, $allowedExtensions, true)
        ) {
            throw new Exception('Invalid image file type.');
        }

        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new Exception('Uploaded file is not a valid image.');
        }

        // Re-encode the image to strip any injected payloads (polyglot files)
        $this->reencodeImage($file->getPathname(), $mimeType);

        $fileName = $safeFilename . '.' . $extension;
        $file->move($this->getTargetDirectory(), $fileName);

        $filePath = $this->getTargetDirectory() . '/' . $fileName;

        if (in_array($mimeType, ['image/jpeg', 'image/png'], true) && config('app.convert_to_webp')) {
            $webpFileName = $safeFilename . '.webp';
            $webpFilePath = $this->getTargetDirectory() . '/' . $webpFileName;

            try {
                WebPConvert::convert($filePath, $webpFilePath, []);

                $this->filesystem->remove($filePath);

                return 'assets/uploads/' . $webpFileName;
            } catch (Throwable $e) {
                $this->logger->error('WebP conversion failed: ' . $e->getMessage());

                // Clean up partial webp file if it was created
                if (file_exists($webpFilePath)) {
                    $this->filesystem->remove($webpFilePath);
                }

                // Keep the original file on conversion failure instead of losing both
                return 'assets/uploads/' . $fileName;
            }
        }

        return 'assets/uploads/' . $fileName;
    }

    /**
     * Uploads a ZIP file with security checks.
     *
     * @param int $maxSize Maximum file size in megabytes
     * @return string|null Path to the uploaded file or null in case of an error
     */
    public function uploadZip(UploadedFile $file, int $maxSize): ?string
    {
        $safeFilename = bin2hex(random_bytes(16));
        $extension = $file->guessExtension();

        $allowedMimeTypes = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'];
        $allowedExtensions = ['zip'];

        $this->validateOriginalName($file->getClientOriginalName());

        if (
            $extension === null
            || !in_array($file->getMimeType(), $allowedMimeTypes, true)
            || !in_array($extension, $allowedExtensions, true)
        ) {
            throw new Exception('Invalid ZIP file type.');
        }

        $this->validateFileSize($file, $maxSize);

        $fileName = $safeFilename . '.' . $extension;
        $file->move($this->getTargetDirectory(), $fileName);

        $filePath = $this->getTargetDirectory() . '/' . $fileName;

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            $this->filesystem->remove($filePath);

            throw new Exception('Invalid ZIP file.');
        }

        $zip->close();

        try {
            $this->inspectZipArchive($filePath, [
                'max_total_size' => $this->convertMegabytesToBytes($maxSize),
                'reject_dangerous_extensions' => true,
            ]);
        } catch (Throwable $e) {
            $this->filesystem->remove($filePath);

            throw $e;
        }

        return 'assets/uploads/' . $fileName;
    }

    /**
     * Safely extracts a ZIP archive with Zip Slip protection.
     *
     * @param string $zipPath Path to the ZIP file
     * @param string $destination Directory to extract into
     * @return bool True on success
     */
    public function safeExtractZip(string $zipPath, string $destination, array $options = []): bool
    {
        $this->inspectZipArchive($zipPath, $options);

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new Exception('Cannot open ZIP file.');
        }

        $realDestination = realpath($destination);
        if ($realDestination === false) {
            $zip->close();

            throw new Exception('Extraction destination does not exist.');
        }

        $zip->extractTo($destination);
        $zip->close();

        return true;
    }

    /**
     * Inspect a ZIP archive before extraction or public storage.
     */
    public function inspectZipArchive(string $zipPath, array $options = []): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new Exception('Cannot open ZIP file.');
        }

        $maxEntries = (int) ( $options['max_entries'] ?? self::DEFAULT_ZIP_MAX_ENTRIES );
        $maxTotalSize = (int) ( $options['max_total_size'] ?? self::DEFAULT_ZIP_MAX_TOTAL_SIZE );
        $minCompressionRatio = (float) ( $options['min_compression_ratio'] ?? self::DEFAULT_ZIP_MIN_COMPRESSION_RATIO );
        $rejectDangerousExtensions = (bool) ( $options['reject_dangerous_extensions'] ?? false );
        $allowedExtensions = $options['allowed_extensions'] ?? null;
        if (is_array($allowedExtensions)) {
            $allowedExtensions = array_map(static fn($ext) => strtolower((string) $ext), $allowedExtensions);
        }

        if ($zip->numFiles > $maxEntries) {
            $zip->close();

            throw new Exception('ZIP contains too many files.');
        }

        $totalSize = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                $zip->close();

                throw new Exception('Invalid ZIP entry.');
            }

            $normalized = str_replace('\\', '/', $entryName);
            $this->assertSafeZipEntryPath($normalized);

            $isDirectory = str_ends_with($normalized, '/');
            $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

            if (!$isDirectory && $allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
                $zip->close();

                throw new Exception('ZIP contains unsupported file type.');
            }

            if (!$isDirectory && $rejectDangerousExtensions && $this->hasDangerousExtension($normalized)) {
                $zip->close();

                throw new Exception('ZIP contains dangerous file type.');
            }

            $stats = $zip->statIndex($i);
            if (!is_array($stats)) {
                $zip->close();

                throw new Exception('Invalid ZIP entry metadata.');
            }

            $size = (int) ( $stats['size'] ?? 0 );
            $compressedSize = (int) ( $stats['comp_size'] ?? 0 );
            $totalSize += $size;

            if ($totalSize > $maxTotalSize) {
                $zip->close();

                throw new Exception('ZIP extracted size exceeds the maximum limit.');
            }

            if ($size > 0 && ( $compressedSize <= 0 || ( $compressedSize / $size ) < $minCompressionRatio )) {
                $zip->close();

                throw new Exception('ZIP compression ratio is suspicious.');
            }
        }

        $zip->close();
    }

    /**
     * Remove an old uploaded file if it exists in the uploads directory.
     * Prevents path traversal by verifying the file is within the uploads dir.
     */
    public function removeUploadedFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (str_starts_with($relativePath, 'assets/img/')) {
            return;
        }

        $fullPath = BASE_PATH . '/public/' . $relativePath;
        $uploadsDir = realpath($this->getTargetDirectory());

        if ($uploadsDir === false) {
            return;
        }

        $realPath = realpath($fullPath);
        if ($realPath !== false && str_starts_with($realPath, $uploadsDir) && is_file($realPath)) {
            $this->filesystem->remove($realPath);
        }
    }

    /**
     * Returns the target directory for file uploads.
     *
     * @return string
     */
    public function getTargetDirectory()
    {
        return BASE_PATH . $this->targetDirectory;
    }

    /**
     * Reject paths that escape the extraction root (works before subdirs exist; handles mixed slashes).
     */
    private function zipArchiveEntryPathStaysInsideRoot(string $normalizedEntryPath): bool
    {
        $parts = array_values(array_filter(
            explode('/', $normalizedEntryPath),
            static fn(string $p): bool => $p !== '',
        ));

        $depth = 0;
        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }

                continue;
            }
            $depth++;
        }

        return true;
    }

    private function assertSafeZipEntryPath(string $normalizedEntryPath): void
    {
        if (
            $normalizedEntryPath === ''
            || str_starts_with($normalizedEntryPath, '/')
            || str_contains($normalizedEntryPath, "\0")
            || !$this->zipArchiveEntryPathStaysInsideRoot($normalizedEntryPath)
        ) {
            throw new Exception('ZIP Slip detected.');
        }
    }

    private function hasDangerousExtension(string $path): bool
    {
        $pattern = '/\.(' . implode('|', self::DANGEROUS_EXTENSIONS) . ')(\.|$)/i';

        return preg_match($pattern, basename($path)) === 1;
    }

    /**
     * Re-encodes an image to strip any injected payloads (EXIF, comments, polyglot data).
     */
    private function reencodeImage(string $path, string $mimeType): void
    {
        $image = @imagecreatefromstring(file_get_contents($path));

        if ($image === false) {
            return;
        }

        try {
            switch ($mimeType) {
                case 'image/jpeg':
                    imagejpeg($image, $path, 90);

                    break;
                case 'image/png':
                    imagesavealpha($image, true);
                    imagepng($image, $path, 9);

                    break;
                case 'image/gif':
                    imagegif($image, $path);

                    break;
                case 'image/webp':
                    imagewebp($image, $path, 90);

                    break;
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Validates the original filename for suspicious extensions.
     */
    private function validateOriginalName(string $originalName): void
    {
        // Strip null bytes
        $originalName = str_replace("\0", '', $originalName);

        // Check for any dangerous extension anywhere in the filename
        $pattern = '/\.(' . implode('|', self::DANGEROUS_EXTENSIONS) . ')(\.|$)/i';
        if (preg_match($pattern, $originalName)) {
            throw new Exception('Suspicious file extension detected.');
        }
    }

    /**
     * Validates file size with safe false-check.
     */
    private function validateFileSize(UploadedFile $file, int $maxSize): void
    {
        $fileSize = $file->getSize();
        $maxSizeBytes = $this->convertMegabytesToBytes($maxSize);

        if ($fileSize === false || $fileSize === 0 || $fileSize > $maxSizeBytes) {
            throw new Exception('File size exceeds the maximum limit of ' . $maxSize . ' MB.');
        }
    }

    /**
     * Converts megabytes to bytes.
     *
     * @param float $megabytes Size in megabytes
     * @return int Size in bytes
     */
    private function convertMegabytesToBytes(float $megabytes): int
    {
        return (int) round($megabytes * 1024 * 1024);
    }
}
