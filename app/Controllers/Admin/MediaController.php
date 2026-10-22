<?php

declare(strict_types=1);

namespace AxaKenneth\Controllers\Admin;

use AxaKenneth\Repositories\MediaRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Config;
use AxaKenneth\Support\Flash;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use Throwable;

final class MediaController extends BaseAdminController
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function index(): void
    {
        $repository = new MediaRepository();
        $page = Http::queryInt('page', 1, 1, 100000);
        $perPage = 24;
        $total = $repository->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $items = $repository->all($perPage, ($page - 1) * $perPage);
        foreach ($items as &$item) {
            $item['usage_count'] = $repository->usageCount((int) $item['id']);
        }
        unset($item);

        $this->render('media/index', 'media', [
            'pageTitle' => 'Media library',
            'mediaItems' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'maxMegabytes' => (int) ceil(((int) Config::get('media.max_bytes', 5242880)) / 1048576),
        ]);
    }

    public function upload(): void
    {
        $admin = Auth::admin();
        if ($admin === null) {
            throw new HttpException(403, 'Your session is no longer valid.');
        }

        $file = $_FILES['image'] ?? null;
        if (!is_array($file)) {
            Flash::error('Choose an image to upload.');
            $this->redirect('media');
        }

        $uploadError = isset($file['error']) && is_int($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE;
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'The image exceeds the allowed upload size.',
            UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded image.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
        ];
        if ($uploadError !== UPLOAD_ERR_OK) {
            Flash::error($errorMessages[$uploadError] ?? 'The image could not be uploaded.');
            $this->redirect('media');
        }

        $temporaryPath = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        $maxBytes = (int) Config::get('media.max_bytes', 5242880);

        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            Flash::error('The uploaded file could not be verified.');
            $this->redirect('media');
        }

        // Never trust the client-supplied size. Check the file that PHP actually
        // received, and store that measured value in the media record.
        $size = @filesize($temporaryPath);
        if (!is_int($size) || $size <= 0 || $size > $maxBytes) {
            Flash::error('The image must be no larger than ' . (int) ceil($maxBytes / 1048576) . ' MB.');
            $this->redirect('media');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporaryPath);
        if (!is_string($mime) || !array_key_exists($mime, self::ALLOWED_MIME)) {
            Flash::error('Upload a JPEG, PNG, or WebP image.');
            $this->redirect('media');
        }

        $dimensions = @getimagesize($temporaryPath);
        if ($dimensions === false) {
            Flash::error('The uploaded file is not a readable image.');
            $this->redirect('media');
        }
        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 60000000) {
            Flash::error('The image dimensions are too large. Use an image no larger than 12000 by 12000 pixels.');
            $this->redirect('media');
        }

        $altText = mb_substr($this->posted('alt_text'), 0, 190);
        $title = mb_substr($this->posted('title'), 0, 190);

        $rawName = isset($file['name']) && is_string($file['name']) ? $file['name'] : 'image';
        $originalName = str_replace('\\', '/', $rawName);
        $originalName = basename($originalName);
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? 'image';
        $originalName = mb_substr(trim($originalName) !== '' ? trim($originalName) : 'image', 0, 255);

        $directory = (string) Config::get('media.path');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the media storage directory.');
        }

        $extension = self::ALLOWED_MIME[$mime];
        $storageKey = bin2hex(random_bytes(20)) . '.' . $extension;
        $targetPath = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $storageKey;

        if (!move_uploaded_file($temporaryPath, $targetPath)) {
            Flash::error('The image could not be saved. Please try again.');
            $this->redirect('media');
        }
        @chmod($targetPath, 0600);

        try {
            $checksum = hash_file('sha256', $targetPath);
            if (!is_string($checksum)) {
                throw new \RuntimeException('Unable to checksum the uploaded image.');
            }

            $id = (new MediaRepository())->create([
                'storage_key' => $storageKey,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'extension' => $extension,
                'size_bytes' => $size,
                'width' => $width,
                'height' => $height,
                'alt_text' => $altText,
                'title' => $title,
                'checksum' => $checksum,
                'uploaded_by' => (int) $admin['id'],
            ]);
        } catch (Throwable $exception) {
            @unlink($targetPath);
            throw $exception;
        }

        AuditLogger::record((int) $admin['id'], 'media.uploaded', 'media', $id, [
            'mime_type' => $mime,
            'size_bytes' => $size,
        ]);

        Flash::success('Image uploaded.');
        $this->redirect('media');
    }

    public function delete(): void
    {
        $repository = new MediaRepository();
        $id = Http::queryInt('id', 0, 1, PHP_INT_MAX);
        $media = $repository->find($id);
        if ($media === null) {
            throw new HttpException(404, 'Media item not found.');
        }

        $usage = $repository->usageCount($id);
        if ($usage > 0) {
            Flash::error('This image is still used by ' . $usage . ' record(s). Replace those images before deleting it.');
            $this->redirect('media');
        }

        $storageKey = (string) $media['storage_key'];
        if ($storageKey === '' || basename($storageKey) !== $storageKey || str_contains($storageKey, '..')) {
            throw new HttpException(500, 'The stored filename is invalid.');
        }

        $basePath = realpath((string) Config::get('media.path'));
        $filePath = $basePath !== false ? realpath($basePath . DIRECTORY_SEPARATOR . $storageKey) : false;
        if ($filePath !== false) {
            if (!str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR)) {
                throw new HttpException(500, 'The stored file is outside the media directory.');
            }
            if (is_file($filePath) && !unlink($filePath)) {
                Flash::error('The image file could not be deleted.');
                $this->redirect('media');
            }
        }

        $repository->delete($id);
        $admin = Auth::admin();
        if ($admin !== null) {
            AuditLogger::record((int) $admin['id'], 'media.deleted', 'media', $id, [
                'original_name' => (string) $media['original_name'],
            ]);
        }

        Flash::success('Image deleted.');
        $this->redirect('media');
    }
}
