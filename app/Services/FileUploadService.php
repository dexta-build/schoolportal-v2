<?php

declare(strict_types=1);

namespace App\Services;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Asset\File;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class FileUploadService
{
    private UploadApi $uploader;

    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->uploader = new UploadApi;
    }

    /**
     * Upload a student/user avatar to Cloudinary.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadAvatar(UploadedFile $file, int $schoolId): array
    {
        $result = $this->retryUpload(fn () => $this->uploader->upload($file->getRealPath(), [
            'folder' => "schoolportal/{$schoolId}/avatars",
            'transformation' => [
                'width' => 400,
                'height' => 400,
                'crop' => 'fill',
                'gravity' => 'face',
                'quality' => 'auto',
                'format' => 'webp',
            ],
        ]));

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Upload a school logo to Cloudinary.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadSchoolLogo(UploadedFile $file, int $schoolId): array
    {
        $result = $this->retryUpload(fn () => $this->uploader->upload($file->getRealPath(), [
            'folder' => "schoolportal/{$schoolId}/branding",
            'resource_type' => 'image',
        ]));

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Delete an image from Cloudinary by its public ID.
     * Returns true on success, false if the delete fails (logs a warning rather
     * than throwing, so controllers do not 500 when Cloudinary is unreachable).
     */
    public function delete(string $publicId): bool
    {
        try {
            $this->uploader->destroy($publicId);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed for image', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete a file whose resource type isn't stored (notice attachments are images or raw documents,
     * assignment files are raw). Cloudinary answers "not found" for the wrong type, so try both.
     */
    public function deleteAnyType(string $publicId): bool
    {
        try {
            foreach (['image', 'raw'] as $resourceType) {
                $response = $this->uploader->destroy($publicId, ['resource_type' => $resourceType]);
                if (($response['result'] ?? null) === 'ok') {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove everything a deleted school stored on Cloudinary (schoolportal/{id}/…): images, documents
     * and videos, then the empty folders. Returns how many files were deleted. Throws on API errors so
     * the queued job retries.
     */
    public function deleteSchoolFolder(int $schoolId): int
    {
        $root = "schoolportal/{$schoolId}";
        $admin = new AdminApi;
        $deleted = 0;

        foreach (['image', 'raw', 'video'] as $resourceType) {
            // Each call removes up to 1000 files; "partial" means there are more
            do {
                $response = $admin->deleteAssetsByPrefix("{$root}/", ['resource_type' => $resourceType]);
                $deleted += count($response['deleted'] ?? []);
            } while ($response['partial'] ?? false);
        }

        // Folders can only go once empty, deepest first. Not worth failing the job over.
        try {
            foreach ($admin->subFolders($root)['folders'] ?? [] as $folder) {
                $admin->deleteFolder($folder['path']);
            }
            $admin->deleteFolder($root);
        } catch (\Throwable $e) {
            Log::info('Could not remove empty Cloudinary folders for deleted school', ['school_id' => $schoolId, 'error' => $e->getMessage()]);
        }

        return $deleted;
    }

    /**
     * Delete a raw file (PDF, DOCX, etc.) from Cloudinary by its public ID.
     * Raw files require resource_type=raw — the default destroy() uses 'image'
     * which would silently return "not found" for raw resources.
     * Returns true on success, false on failure (logs a warning instead of throwing).
     */
    public function deleteRaw(string $publicId): bool
    {
        try {
            $this->uploader->destroy($publicId, ['resource_type' => 'raw']);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed for raw resource', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Upload a notice file (image or document) to Cloudinary.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadNoticeFile(UploadedFile $file, int $schoolId): array
    {
        $isImage = str_starts_with($file->getMimeType() ?? '', 'image/');

        // Use original filename (without extension) as public_id so Cloudinary
        // preserves the file extension in the URL — this ensures downloads
        // have the correct file type instead of a generic "file".
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $uniqueName = $safeName.'_'.uniqid();

        $options = [
            'folder' => "schoolportal/{$schoolId}/notices",
            'public_id' => $uniqueName,
        ];

        $uploadPath = $file->getRealPath();
        $tempFile = null;

        if (! $isImage) {
            $options['resource_type'] = 'raw';
            // For raw files, append extension so Cloudinary URL includes it
            $options['public_id'] = $uniqueName.'.'.$extension;

            // Copy to a temp file with the correct extension so Cloudinary
            // doesn't append .tmp from PHP's temporary upload path.
            $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$uniqueName.'.'.$extension;
            copy($file->getRealPath(), $tempFile);
            $uploadPath = $tempFile;
        }

        try {
            $result = $this->retryUpload(fn () => $this->uploader->upload($uploadPath, $options));
        } finally {
            if ($tempFile) {
                @unlink($tempFile);
            }
        }

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Upload a result file (PDF) to Cloudinary.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadResult(UploadedFile $file, int $schoolId): array
    {
        return $this->uploadRawFile($file, $schoolId, 'results');
    }

    /**
     * Upload a result file to Cloudinary from a local file path.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadResultFromPath(string $filePath, int $schoolId): array
    {
        $originalName = pathinfo($filePath, PATHINFO_FILENAME);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'pdf';
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $uniqueName = $safeName.'_'.uniqid();

        $result = $this->retryUpload(fn () => $this->uploader->upload($filePath, [
            'folder' => "schoolportal/{$schoolId}/results",
            'resource_type' => 'raw',
            'public_id' => $uniqueName.'.'.$extension,
        ]));

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Upload an assignment file to Cloudinary.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadAssignment(UploadedFile $file, int $schoolId): array
    {
        return $this->uploadRawFile($file, $schoolId, 'assignments');
    }

    /**
     * Upload a raw document (PDF, DOCX) for AI exam/quiz generation.
     *
     * @return array{url: string, public_id: string}
     */
    public function uploadRawDocument(UploadedFile $file, int $schoolId): array
    {
        return $this->uploadRawFile($file, $schoolId, 'exam-sources');
    }

    /**
     * Upload a platform email attachment to Cloudinary.
     * Stored in a shared platform folder (not school-scoped).
     * Should be deleted after all recipient emails have been sent.
     *
     * @return array{url: string, public_id: string, name: string, mime: string, size: int}
     */
    public function uploadEmailAttachment(UploadedFile $file): array
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $uniqueName = $safeName.'_'.uniqid();

        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$uniqueName.'.'.$extension;
        copy($file->getRealPath(), $tempFile);

        try {
            $result = $this->retryUpload(fn () => $this->uploader->upload($tempFile, [
                'folder' => 'schoolportal/platform/email-attachments',
                'resource_type' => 'raw',
                'public_id' => $uniqueName.'.'.$extension,
            ]));
        } finally {
            @unlink($tempFile);
        }

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ];
    }

    /**
     * Upload a raw file (PDF, DOCX, etc.) to Cloudinary with a safe unique name.
     *
     * PHP stores uploaded files with a .tmp extension. Cloudinary's raw upload
     * appends the file path's extension to the public_id, so we must copy the
     * file to a temp path with the correct extension before uploading.
     *
     * @return array{url: string, public_id: string}
     */
    private function uploadRawFile(UploadedFile $file, int $schoolId, string $subfolder): array
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $uniqueName = $safeName.'_'.uniqid();

        // Copy to a temp file with the correct extension so Cloudinary
        // doesn't append .tmp from PHP's temporary upload path.
        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.$uniqueName.'.'.$extension;
        copy($file->getRealPath(), $tempFile);

        try {
            $result = $this->retryUpload(fn () => $this->uploader->upload($tempFile, [
                'folder' => "schoolportal/{$schoolId}/{$subfolder}",
                'resource_type' => 'raw',
                'public_id' => $uniqueName.'.'.$extension,
            ]));
        } finally {
            @unlink($tempFile);
        }

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Generate a signed Cloudinary URL for a raw file (PDF, DOCX, etc.).
     *
     * Cloudinary accounts with "Strict Transformations" enabled block
     * unsigned delivery of raw resources, returning HTTP 401.
     * A long signature (32-char SHA-256) is required for strict mode.
     */
    public static function signedRawUrl(string $publicId): string
    {
        self::ensureConfigured();

        return (string) (new File($publicId))
            ->signUrl()
            ->longUrlSignature()
            ->toUrl();
    }

    /**
     * Ensure the Cloudinary Configuration singleton is initialised.
     *
     * This is needed because signedRawUrl() is static and may be called
     * before any FileUploadService instance has been constructed.
     */
    private static function ensureConfigured(): void
    {
        static $configured = false;

        if ($configured) {
            return;
        }

        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $configured = true;
    }

    /**
     * Upload to Cloudinary with exponential-backoff retry on transient failures.
     *
     * @param  callable(): mixed  $fn  Closure that performs the actual upload and returns the result.
     * @param  int  $maxAttempts  Number of upload attempts (default 3).
     * @return mixed Cloudinary upload result.
     *
     * @throws \Throwable Re-throws the last exception after all attempts are exhausted.
     */
    private function retryUpload(callable $fn, int $maxAttempts = 3): mixed
    {
        $lastException = null;
        $delayMs = 2000; // Start at 2 s, doubles on each retry.

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("Cloudinary upload attempt {$attempt}/{$maxAttempts} failed.", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    usleep($delayMs * 1000);
                    $delayMs *= 2;
                }
            }
        }

        throw $lastException;
    }
}
