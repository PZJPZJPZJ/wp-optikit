<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Support\SizeFormatter;

final class ImageProcessor
{
    /**
     * @var array<string, array{old_path:string, old_size:int}>
     */
    private array $pendingUploadConversions = array();

    public function __construct(private readonly ImageSettings $settings)
    {
    }

    public function handleUpload(array $upload): array
    {
        if (!$this->settings->isEnabled() || empty($upload['file']) || empty($upload['type'])) {
            return $upload;
        }

        if (!in_array((string) $upload['type'], $this->allowedMimeTypes(), true)) {
            return $upload;
        }

        $oldPath    = (string) $upload['file'];
        $oldSize    = file_exists($oldPath) ? (int) filesize($oldPath) : 0;
        $savedImage = $this->convertFileToWebp($oldPath);

        if ($savedImage === null) {
            return $upload;
        }

        $this->pendingUploadConversions[$savedImage['path']] = array(
            'old_path' => $oldPath,
            'old_size' => $oldSize,
        );

        $upload['file'] = $savedImage['path'];
        $upload['url']  = str_replace(basename((string) $upload['url']), basename($savedImage['path']), (string) $upload['url']);
        $upload['type'] = 'image/webp';

        return $upload;
    }

    public function handleAttachmentMetadata(array $metadata, int $attachmentId): array
    {
        $attachedFile = get_attached_file($attachmentId);

        if (is_string($attachedFile) && isset($this->pendingUploadConversions[$attachedFile])) {
            $conversion = $this->pendingUploadConversions[$attachedFile];
            update_post_meta(
                $attachmentId,
                '_wpok_last_conversion',
                $this->buildResult('succeeded', $attachmentId, $conversion['old_path'], $attachedFile, $conversion['old_size'])
            );
            $this->deleteFiles(array($conversion['old_path']), array($attachedFile));
            unset($this->pendingUploadConversions[$attachedFile]);
        }

        return $metadata;
    }

    public function convertAttachmentToWebp(int $attachmentId): array
    {
        $filePath = get_attached_file($attachmentId);

        if (!is_string($filePath) || !file_exists($filePath)) {
            throw new \RuntimeException('Attachment file does not exist.');
        }

        if (preg_match('/\.webp$/i', $filePath)) {
            return $this->buildResult('skipped', $attachmentId, $filePath, $filePath);
        }

        $oldSize      = (int) filesize($filePath);
        $metadata     = wp_get_attachment_metadata($attachmentId);
        $metadata     = is_array($metadata) ? $metadata : array();
        $oldMimeType  = (string) get_post_mime_type($attachmentId);
        $savedImage   = $this->convertFileToWebp($filePath);

        if ($savedImage === null) {
            if (strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)) === 'gif') {
                return $this->buildResult('skipped', $attachmentId, $filePath, $filePath);
            }

            throw new \RuntimeException('Attachment conversion failed.');
        }

        try {
            $convertedOriginal = $this->convertOriginalImage($filePath, $metadata);
        } catch (\Throwable $exception) {
            $this->deleteGeneratedConversionFiles($savedImage, null, array());
            throw $exception;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $newMetadata = wp_generate_attachment_metadata($attachmentId, $savedImage['path']);

        if (!is_array($newMetadata)) {
            $this->deleteGeneratedConversionFiles($savedImage, $convertedOriginal, array());
            throw new \RuntimeException('Attachment metadata generation failed.');
        }

        if (is_array($convertedOriginal)) {
            $newMetadata['original_image'] = basename($convertedOriginal['path']);
        }

        $newFiles = $this->metadataFilePaths($newMetadata);
        $dbChanged = false;

        try {
            if (!update_attached_file($attachmentId, $savedImage['path'])) {
                throw new \RuntimeException('Unable to update attachment file.');
            }

            $dbChanged = true;

            $postId = wp_update_post(
                array(
                    'ID'             => $attachmentId,
                    'post_mime_type' => 'image/webp',
                ),
                true
            );

            if (is_wp_error($postId) || (int) $postId <= 0) {
                throw new \RuntimeException('Unable to update attachment mime type.');
            }

            if (wp_update_attachment_metadata($attachmentId, $newMetadata) === false) {
                throw new \RuntimeException('Unable to update attachment metadata.');
            }
        } catch (\Throwable $exception) {
            if ($dbChanged) {
                update_attached_file($attachmentId, $filePath);
                wp_update_attachment_metadata($attachmentId, $metadata);
                wp_update_post(
                    array(
                        'ID'             => $attachmentId,
                        'post_mime_type' => $oldMimeType,
                    )
                );
            }

            $this->deleteGeneratedConversionFiles($savedImage, $convertedOriginal, $newFiles);
            throw $exception;
        }

        $oldFiles = $this->metadataFilePaths($metadata, dirname($filePath));
        $oldFiles[] = $filePath;
        $protectedFiles = array_merge($newFiles, array($savedImage['path']));

        if (is_array($convertedOriginal)) {
            $protectedFiles[] = $convertedOriginal['path'];
        }

        $this->deleteFiles($oldFiles, $protectedFiles);

        $result = $this->buildResult('succeeded', $attachmentId, $filePath, $savedImage['path'], $oldSize);
        update_post_meta($attachmentId, '_wpok_last_conversion', $result);

        return $result;
    }

    public function recompressAttachment(int $attachmentId): array
    {
        $filePath = get_attached_file($attachmentId);

        if (!is_string($filePath) || !file_exists($filePath)) {
            throw new \RuntimeException('Attachment file does not exist.');
        }

        $oldSize     = (int) filesize($filePath);
        $oldMetadata = wp_get_attachment_metadata($attachmentId);
        $oldMetadata = is_array($oldMetadata) ? $oldMetadata : array();
        $saved       = $this->recompressFile($filePath);

        if ($saved === null) {
            throw new \RuntimeException('Image recompression failed.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $newMetadata = wp_generate_attachment_metadata($attachmentId, $filePath);
        $newMetadata = is_array($newMetadata) ? $newMetadata : array();

        if (!empty($oldMetadata['original_image']) && empty($newMetadata['original_image'])) {
            $newMetadata['original_image'] = $oldMetadata['original_image'];
        }

        wp_update_attachment_metadata($attachmentId, $newMetadata);

        $result = $this->buildResult('succeeded', $attachmentId, $filePath, $filePath, $oldSize);
        update_post_meta($attachmentId, '_wpok_last_recompress', $result);

        return $result;
    }

    public function regenerateMissingThumbnails(int $attachmentId): array
    {
        $filePath = get_attached_file($attachmentId);

        if (!is_string($filePath) || !file_exists($filePath)) {
            return $this->buildThumbnailResult(
                'skipped',
                $attachmentId,
                is_string($filePath) ? $filePath : '',
                0,
                'Attachment file is missing. Use Missing Files cleanup first.'
            );
        }

        $mimeType = (string) get_post_mime_type($attachmentId);

        if (str_starts_with($mimeType, 'image/')) {
            return $this->regenerateImageThumbnails($attachmentId, $filePath);
        }

        if ($mimeType === 'application/pdf') {
            return $this->regeneratePdfPreview($attachmentId, $filePath);
        }

        return $this->buildThumbnailResult(
            'skipped',
            $attachmentId,
            $filePath,
            0,
            'Unsupported attachment type.'
        );
    }

    private function convertFileToWebp(string $filePath): ?array
    {
        if (!file_exists($filePath) || !$this->hasImageEditorSupport()) {
            return null;
        }

        if (strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)) === 'gif') {
            if ($this->settings->getEngine() === 'imagick' && $this->hasAnimatedWebpSupport()) {
                return $this->convertAnimatedGifToWebp($filePath);
            }

            return null;
        }

        $editor = wp_get_image_editor($filePath);

        if (is_wp_error($editor)) {
            return null;
        }

        $editor->set_quality($this->settings->getQuality());
        $fileInfo = pathinfo($filePath);
        $webpPath = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.webp';

        if (file_exists($webpPath)) {
            return null;
        }

        $saved    = $editor->save($webpPath, 'image/webp');

        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            return null;
        }

        return array(
            'path' => (string) $saved['path'],
            'file' => basename((string) $saved['path']),
        );
    }

    private function regenerateImageThumbnails(int $attachmentId, string $filePath): array
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_get_attachment_metadata($attachmentId);
        $metadata = is_array($metadata) ? $metadata : array();
        $originalImage = $metadata['original_image'] ?? null;
        $missingBefore = $this->missingImageThumbnailNames($attachmentId, $filePath, $metadata);

        if (empty($missingBefore)) {
            return $this->buildThumbnailResult('skipped', $attachmentId, $filePath, 0, 'No missing thumbnails found.');
        }

        $metadata = $this->removeMissingSizeMetadata($metadata, $filePath);
        wp_update_attachment_metadata($attachmentId, $metadata);

        $updatedMetadata = wp_update_image_subsizes($attachmentId);

        if (!is_array($updatedMetadata)) {
            throw new \RuntimeException('WordPress could not regenerate image subsizes.');
        }

        if (!empty($originalImage) && empty($updatedMetadata['original_image'])) {
            $updatedMetadata['original_image'] = $originalImage;
            wp_update_attachment_metadata($attachmentId, $updatedMetadata);
        }

        $missingAfter = $this->missingImageThumbnailNames($attachmentId, $filePath, $updatedMetadata);
        $regenerated = array_values(array_diff($missingBefore, $missingAfter));

        if (empty($regenerated)) {
            throw new \RuntimeException('Missing thumbnails could not be regenerated.');
        }

        return $this->buildThumbnailResult(
            'succeeded',
            $attachmentId,
            $filePath,
            count($regenerated),
            'Regenerated missing thumbnails.',
            $regenerated
        );
    }

    private function regeneratePdfPreview(int $attachmentId, string $filePath): array
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $oldMetadata = wp_get_attachment_metadata($attachmentId);
        $oldMetadata = is_array($oldMetadata) ? $oldMetadata : array();
        $missingBefore = $this->missingPdfPreviewNames($filePath, $oldMetadata);

        if (empty($missingBefore)) {
            return $this->buildThumbnailResult('skipped', $attachmentId, $filePath, 0, 'No missing PDF preview found.');
        }

        $newMetadata = wp_generate_attachment_metadata($attachmentId, $filePath);

        if (!is_array($newMetadata) || empty($newMetadata['sizes']) || !is_array($newMetadata['sizes'])) {
            return $this->buildThumbnailResult(
                'skipped',
                $attachmentId,
                $filePath,
                0,
                'PDF preview generation is not supported on this server.'
            );
        }

        if (!empty($oldMetadata['original_image']) && empty($newMetadata['original_image'])) {
            $newMetadata['original_image'] = $oldMetadata['original_image'];
        }

        wp_update_attachment_metadata($attachmentId, $newMetadata);

        $missingAfter = $this->missingPdfPreviewNames($filePath, $newMetadata);
        $regenerated = array_values(array_diff($missingBefore, $missingAfter));

        if (empty($regenerated)) {
            throw new \RuntimeException('Missing PDF preview could not be regenerated.');
        }

        return $this->buildThumbnailResult(
            'succeeded',
            $attachmentId,
            $filePath,
            count($regenerated),
            'Regenerated missing PDF preview.',
            $regenerated
        );
    }

    /**
     * @return array<int, string>
     */
    private function missingImageThumbnailNames(int $attachmentId, string $filePath, array $metadata): array
    {
        $missing = array();

        foreach ($this->missingRecordedSizeNames($metadata, $filePath) as $sizeName) {
            $missing[$sizeName] = true;
        }

        if (function_exists('wp_get_missing_image_subsizes')) {
            foreach (array_keys(wp_get_missing_image_subsizes($attachmentId)) as $sizeName) {
                $missing[(string) $sizeName] = true;
            }
        }

        return array_keys($missing);
    }

    /**
     * @return array<int, string>
     */
    private function missingPdfPreviewNames(string $filePath, array $metadata): array
    {
        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return array('pdf-preview');
        }

        return $this->missingRecordedSizeNames($metadata, $filePath);
    }

    /**
     * @return array<int, string>
     */
    private function missingRecordedSizeNames(array $metadata, string $filePath): array
    {
        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return array();
        }

        $missing = array();
        $directory = $this->metadataDirectory($metadata, dirname($filePath));

        foreach ($metadata['sizes'] as $sizeName => $size) {
            if (empty($size['file'])) {
                continue;
            }

            if (!file_exists($directory . '/' . (string) $size['file'])) {
                $missing[] = (string) $sizeName;
            }
        }

        return $missing;
    }

    private function removeMissingSizeMetadata(array $metadata, string $filePath): array
    {
        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return $metadata;
        }

        $directory = $this->metadataDirectory($metadata, dirname($filePath));

        foreach ($metadata['sizes'] as $sizeName => $size) {
            if (empty($size['file'])) {
                continue;
            }

            if (!file_exists($directory . '/' . (string) $size['file'])) {
                unset($metadata['sizes'][$sizeName]);
            }
        }

        return $metadata;
    }

    private function metadataDirectory(array $metadata, string $fallbackDirectory): string
    {
        if (!empty($metadata['file'])) {
            return dirname($this->absoluteUploadPath((string) $metadata['file']));
        }

        return $this->normalizePath($fallbackDirectory);
    }

    private function recompressFile(string $filePath): ?array
    {
        if (!file_exists($filePath) || !$this->hasImageEditorSupport()) {
            return null;
        }

        $editor = wp_get_image_editor($filePath);

        if (is_wp_error($editor)) {
            return null;
        }

        $editor->set_quality($this->settings->getQuality());

        $fileInfo = pathinfo($filePath);
        $tempPath = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.tmp.' . $fileInfo['extension'];
        $mimeType = $this->mimeTypeForExtension((string) $fileInfo['extension']);
        $saved    = $editor->save($tempPath, $mimeType);

        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            @unlink($tempPath);
            return null;
        }

        if (!@rename((string) $saved['path'], $filePath)) {
            @unlink((string) $saved['path']);
            return null;
        }

        return array(
            'path' => $filePath,
            'file' => basename($filePath),
        );
    }

    private function convertOriginalImage(string $filePath, array $metadata): ?array
    {
        if (empty($metadata['original_image'])) {
            return null;
        }

        $originalFile = dirname($filePath) . '/' . $metadata['original_image'];

        if (!file_exists($originalFile) || preg_match('/\.webp$/i', $originalFile)) {
            return null;
        }

        $converted = $this->convertFileToWebp($originalFile);

        if ($converted === null) {
            throw new \RuntimeException('Original image conversion failed.');
        }

        return $converted;
    }

    /**
     * @return array<int, string>
     */
    private function metadataFilePaths(array $metadata, ?string $fallbackDirectory = null): array
    {
        $paths = array();
        $directory = $fallbackDirectory;

        if (!empty($metadata['file'])) {
            $mainFile = $this->absoluteUploadPath((string) $metadata['file']);
            $paths[] = $mainFile;
            $directory = dirname($mainFile);
        }

        if ($directory === null || $directory === '') {
            return array_values(array_unique(array_map(array($this, 'normalizePath'), $paths)));
        }

        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $paths[] = $directory . '/' . (string) $size['file'];
                }
            }
        }

        if (!empty($metadata['original_image'])) {
            $paths[] = $directory . '/' . (string) $metadata['original_image'];
        }

        return array_values(array_unique(array_map(array($this, 'normalizePath'), $paths)));
    }

    private function absoluteUploadPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $this->normalizePath($path);
        }

        $uploadDir = wp_get_upload_dir();

        return $this->normalizePath((string) $uploadDir['basedir'] . '/' . ltrim($path, '/\\'));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function normalizePath(string $path): string
    {
        return wp_normalize_path($path);
    }

    /**
     * @param array{path:string,file:string} $savedImage
     * @param array{path:string,file:string}|null $convertedOriginal
     * @param array<int, string> $metadataFiles
     */
    private function deleteGeneratedConversionFiles(array $savedImage, ?array $convertedOriginal, array $metadataFiles): void
    {
        $files = array_merge($metadataFiles, array((string) $savedImage['path']));

        if (is_array($convertedOriginal)) {
            $files[] = (string) $convertedOriginal['path'];
        }

        $this->deleteFiles($files);
    }

    /**
     * @param array<int, string> $files
     * @param array<int, string> $protectedFiles
     */
    private function deleteFiles(array $files, array $protectedFiles = array()): void
    {
        $protected = array();

        foreach ($protectedFiles as $protectedFile) {
            if ($protectedFile !== '') {
                $protected[$this->normalizePath($protectedFile)] = true;
            }
        }

        foreach (array_unique(array_map(array($this, 'normalizePath'), $files)) as $file) {
            if ($file === '' || isset($protected[$file]) || is_dir($file) || !file_exists($file)) {
                continue;
            }

            @unlink($file);
        }
    }

    /**
     * 检查 Imagick 是否支持 WebP 格式
     */
    public function hasImagickWebpSupport(): bool
    {
        if (!extension_loaded('imagick')) {
            return false;
        }

        try {
            $formats = \Imagick::queryFormats('WEBP');

            return !empty($formats);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 检查 Imagick 是否支持动画 WebP（libwebp-anim）
     */
    /**
     * Check whether Imagick can encode animated WebP on this runtime.
     */
    public function hasAnimatedWebpSupport(): bool
    {
        if (!extension_loaded('imagick')) {
            return false;
        }

        if (!$this->hasImagickWebpSupport()) {
            return false;
        }

        $cacheKey = 'wpok_anim_webp_support';
        $cached   = get_transient($cacheKey);

        if ($cached !== false) {
            return (bool) $cached;
        }

        $tempPath = null;

        try {
            $frame1 = new \Imagick();
            $frame1->newImage(1, 1, new \ImagickPixel('white'));
            $frame1->setImageFormat('webp');
            $frame1->setImageDelay(10);

            $frame2 = clone $frame1;
            $frame2->setImageDelay(20);

            $anim = new \Imagick();
            $anim->addImage($frame1);
            $anim->addImage($frame2);
            $anim->setImageFormat('webp');
            $anim->setImageIterations(0);

            $tempPath = $this->buildTemporaryAnimatedWebpPath();
            $anim->writeImages($tempPath, true);

            $anim->clear();
            $frame1->clear();
            $frame2->clear();

            $supported = file_exists($tempPath) && $this->isAnimatedWebpFile($tempPath);

            @unlink($tempPath);
            set_transient($cacheKey, $supported, HOUR_IN_SECONDS);

            return $supported;
        } catch (\Throwable $e) {
            if (is_string($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            set_transient($cacheKey, false, HOUR_IN_SECONDS);

            return false;
        }
    }

    /**
     * 获取引擎支持状态（供 UI 使用）
     *
     * @return array<string, bool>
     */
    public function getEngineStatus(): array
    {
        return array(
            'gd_loaded'      => extension_loaded('gd'),
            'imagick_loaded' => extension_loaded('imagick'),
        );
    }

    /**
     * 使用 Imagick 将动画 GIF 转换为动画 WebP
     */
    private function convertAnimatedGifToWebp(string $filePath): ?array
    {
        try {
            $fileInfo = pathinfo($filePath);
            $webpPath = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.webp';

            if (file_exists($webpPath)) {
                return null;
            }

            $source     = new \Imagick($filePath);
            $iterations = $source->getImageIterations();
            $imagick    = $source->coalesceImages();

            $source->clear();

            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($this->settings->getQuality());
            $imagick->setImageIterations($iterations);

            foreach ($imagick as $frame) {
                $frame->setImageFormat('webp');
                $frame->setImageCompressionQuality($this->settings->getQuality());
                $frame->setImageDelay($frame->getImageDelay());
                $frame->setImageDispose($frame->getImageDispose());
            }

            $imagick->writeImages($webpPath, true);
            $imagick->clear();

            if (!file_exists($webpPath) || !$this->isAnimatedWebpFile($webpPath)) {
                @unlink($webpPath);
                return null;
            }

            return array(
                'path' => $webpPath,
                'file' => basename($webpPath),
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildTemporaryAnimatedWebpPath(): string
    {
        $tempPath = tempnam(get_temp_dir(), 'wpok');

        if (!is_string($tempPath) || $tempPath === '') {
            throw new \RuntimeException('Unable to create a temporary file.');
        }

        $webpPath = $tempPath . '.webp';
        @unlink($tempPath);

        return $webpPath;
    }

    private function isAnimatedWebpFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $contents = @file_get_contents($filePath);

        if (!is_string($contents) || $contents === '') {
            return false;
        }

        return str_contains($contents, 'ANIM') || str_contains($contents, 'ANMF');
    }

    private function buildResult(string $status, int $attachmentId, string $oldPath, string $newPath, ?int $oldSize = null): array
    {
        $oldSize ??= file_exists($oldPath) ? (int) filesize($oldPath) : 0;
        $newSize = file_exists($newPath) ? (int) filesize($newPath) : 0;

        return array(
            'status'             => $status,
            'attachment_id'      => $attachmentId,
            'old_path'           => $oldPath,
            'new_path'           => $newPath,
            'old_size_bytes'     => $oldSize,
            'new_size_bytes'     => $newSize,
            'old_size_formatted' => SizeFormatter::formatBytes($oldSize),
            'new_size_formatted' => SizeFormatter::formatBytes($newSize),
            'timestamp'          => current_time('mysql', true),
        );
    }

    /**
     * @param array<int, string> $sizes
     * @return array<string, mixed>
     */
    private function buildThumbnailResult(
        string $status,
        int $attachmentId,
        string $filePath,
        int $regeneratedCount,
        string $message,
        array $sizes = array()
    ): array {
        $path = $filePath !== '' ? $filePath : 'missing';
        $result = $this->buildResult($status, $attachmentId, $path, $path);
        $result['regenerated_count'] = $regeneratedCount;
        $result['message'] = $message;
        $result['sizes'] = array_values($sizes);

        return $result;
    }

    private function allowedMimeTypes(): array
    {
        $map   = array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        );
        $mimes = array();

        foreach ($this->settings->getFormats() as $format) {
            if (isset($map[$format])) {
                $mimes[] = $map[$format];
            }
        }

        return $mimes;
    }

    private function hasImageEditorSupport(): bool
    {
        return extension_loaded('imagick') || extension_loaded('gd');
    }

    private function mimeTypeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
