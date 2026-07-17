<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Contracts\JobHandlerInterface;

final class ImageJobHandler implements JobHandlerInterface
{
    public function __construct(private readonly ImageProcessor $processor)
    {
    }

    public function get_module(): string
    {
        return 'image';
    }

    public function supports_job_type(string $jobType): bool
    {
        return in_array($jobType, array('image_convert', 'image_recompress', 'thumbnail_regenerate'), true);
    }

    public function build_items(string $jobType, array $payload): array
    {
        $attachmentIds = array_unique(array_map('intval', (array) ($payload['attachment_ids'] ?? array())));
        $items         = array();

        foreach ($attachmentIds as $attachmentId) {
            if ($attachmentId <= 0) {
                continue;
            }

            $filePath = get_attached_file($attachmentId);

            $items[] = array(
                'object_type' => 'attachment',
                'object_id'   => $attachmentId,
                'source_path' => is_string($filePath) ? $this->relativePath($filePath) : '',
            );
        }

        return $items;
    }

    public function process_item(string $jobType, array $job, array $item): array
    {
        try {
            $attachmentId = (int) $item['object_id'];

            if ($jobType === 'image_convert') {
                $result = $this->processor->convertAttachmentToWebp($attachmentId);
                return array(
                    'status' => (string) ($result['status'] ?? 'succeeded'),
                    'result' => $result,
                );
            }

            if ($jobType === 'image_recompress') {
                $result = $this->processor->recompressAttachment($attachmentId);
                return array(
                    'status' => (string) ($result['status'] ?? 'succeeded'),
                    'result' => $result,
                );
            }

            if ($jobType === 'thumbnail_regenerate') {
                $result = $this->processor->regenerateMissingThumbnails($attachmentId);
                return array(
                    'status' => (string) ($result['status'] ?? 'succeeded'),
                    'result' => $result,
                );
            }

            return array(
                'status' => 'failed',
                'error'  => 'Unsupported image job type.',
            );
        } catch (\Throwable $exception) {
            return array(
                'status' => 'failed',
                'error'  => $exception->getMessage(),
            );
        }
    }

    private function relativePath(string $filePath): string
    {
        $uploadDir = wp_upload_dir();
        $baseDir   = wp_normalize_path((string) $uploadDir['basedir']);
        $path      = wp_normalize_path($filePath);

        if (str_starts_with($path, $baseDir)) {
            return ltrim(str_replace($baseDir, '', $path), '/');
        }

        return basename($path);
    }
}
