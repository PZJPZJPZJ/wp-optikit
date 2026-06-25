<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Support\SizeFormatter;

final class ImageProcessor
{
    /**
     * @var array<string, string>
     */
    private array $pendingOriginalFiles = array();

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

        $oldPath      = (string) $upload['file'];
        $oldSize      = file_exists($oldPath) ? (int) filesize($oldPath) : 0;
        $keepOriginal = $this->settings->keepOriginal();
        $savedImage   = $this->convertFileToWebp($oldPath, !$keepOriginal);

        if ($savedImage === null) {
            return $upload;
        }

        $this->pendingUploadConversions[$savedImage['path']] = array(
            'old_path' => $oldPath,
            'old_size' => $oldSize,
        );

        if ($keepOriginal) {
            $this->pendingOriginalFiles[$savedImage['path']] = $oldPath;
        }

        $upload['file'] = $savedImage['path'];
        $upload['url']  = str_replace(basename((string) $upload['url']), basename($savedImage['path']), (string) $upload['url']);
        $upload['type'] = 'image/webp';

        return $upload;
    }

    public function handleAttachmentMetadata(array $metadata, int $attachmentId): array
    {
        $attachedFile = get_attached_file($attachmentId);

        if (is_string($attachedFile) && isset($this->pendingOriginalFiles[$attachedFile])) {
            $this->storeOriginalReference($attachmentId, $this->pendingOriginalFiles[$attachedFile]);
            unset($this->pendingOriginalFiles[$attachedFile]);
        }

        if (is_string($attachedFile) && isset($this->pendingUploadConversions[$attachedFile])) {
            $conversion = $this->pendingUploadConversions[$attachedFile];
            update_post_meta(
                $attachmentId,
                '_wpok_last_conversion',
                $this->buildResult('succeeded', $attachmentId, $conversion['old_path'], $attachedFile, $conversion['old_size'])
            );
            unset($this->pendingUploadConversions[$attachedFile]);
        }

        if (!$this->settings->isEnabled() || empty($metadata['original_image']) || !is_string($attachedFile)) {
            return $metadata;
        }

        $originalFile = dirname($attachedFile) . '/' . $metadata['original_image'];

        if (!file_exists($originalFile) || preg_match('/\.webp$/i', $originalFile)) {
            return $metadata;
        }

        $keepOriginal = $this->settings->keepOriginal();
        $converted    = $this->convertFileToWebp($originalFile, !$keepOriginal);

        if ($converted === null) {
            return $metadata;
        }

        if ($keepOriginal) {
            $this->storeOriginalReference($attachmentId, $originalFile);
        }

        $metadata['original_image'] = basename($converted['path']);

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
        $keepOriginal = $this->settings->keepOriginal();
        $metadata     = wp_get_attachment_metadata($attachmentId);
        $metadata     = is_array($metadata) ? $metadata : array();
        $dirname      = dirname($filePath);

        if (!empty($metadata['original_image'])) {
            $originalFile = $dirname . '/' . $metadata['original_image'];

            if (file_exists($originalFile) && !preg_match('/\.webp$/i', $originalFile)) {
                $convertedOriginal = $this->convertFileToWebp($originalFile, !$keepOriginal);

                if ($convertedOriginal !== null) {
                    $metadata['original_image'] = basename($convertedOriginal['path']);

                    if ($keepOriginal) {
                        $this->storeOriginalReference($attachmentId, $originalFile);
                    }
                }
            }
        }

        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $sizeData) {
                if (empty($sizeData['file'])) {
                    continue;
                }

                $thumbnailPath = $dirname . '/' . $sizeData['file'];

                if (file_exists($thumbnailPath)) {
                    @unlink($thumbnailPath);
                }
            }
        }

        $savedImage = $this->convertFileToWebp($filePath, !$keepOriginal);

        if ($savedImage === null) {
            throw new \RuntimeException('Attachment conversion failed.');
        }

        update_attached_file($attachmentId, $savedImage['path']);
        wp_update_post(
            array(
                'ID'             => $attachmentId,
                'post_mime_type' => 'image/webp',
            )
        );

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $newMetadata = wp_generate_attachment_metadata($attachmentId, $savedImage['path']);
        $newMetadata = is_array($newMetadata) ? $newMetadata : array();

        if (!empty($metadata['original_image'])) {
            $newMetadata['original_image'] = $metadata['original_image'];
        }

        wp_update_attachment_metadata($attachmentId, $newMetadata);

        if ($keepOriginal) {
            $this->storeOriginalReference($attachmentId, $filePath);
        } else {
            delete_post_meta($attachmentId, '_wpok_original_file');
        }

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

        $oldSize = (int) filesize($filePath);
        $saved   = $this->recompressFile($filePath);

        if ($saved === null) {
            throw new \RuntimeException('Image recompression failed.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $newMetadata = wp_generate_attachment_metadata($attachmentId, $filePath);
        wp_update_attachment_metadata($attachmentId, is_array($newMetadata) ? $newMetadata : array());

        $result = $this->buildResult('succeeded', $attachmentId, $filePath, $filePath, $oldSize);
        update_post_meta($attachmentId, '_wpok_last_recompress', $result);

        return $result;
    }

    private function convertFileToWebp(string $filePath, bool $deleteOriginal): ?array
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
        $webpPath = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.webp';
        $saved    = $editor->save($webpPath, 'image/webp');

        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            return null;
        }

        if ($deleteOriginal && $filePath !== $saved['path']) {
            @unlink($filePath);
        }

        return array(
            'path' => (string) $saved['path'],
            'file' => basename((string) $saved['path']),
        );
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

    private function storeOriginalReference(int $attachmentId, string $originalFile): void
    {
        $uploadDir = wp_upload_dir();
        $baseDir   = wp_normalize_path((string) $uploadDir['basedir']);
        $path      = wp_normalize_path($originalFile);
        $relative  = str_starts_with($path, $baseDir) ? ltrim(str_replace($baseDir, '', $path), '/') : basename($path);

        update_post_meta($attachmentId, '_wpok_original_file', $relative);
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
