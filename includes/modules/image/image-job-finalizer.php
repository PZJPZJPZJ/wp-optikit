<?php

namespace WPOptiKit\Modules\Image;

final class ImageJobFinalizer
{
    public function __construct(
        private readonly ImageSettings $settings,
        private readonly ElementorCacheBridge $elementorCache
    ) {
    }

    /**
     * @param array<string, mixed> $job
     */
    public function handleCompletedJob(array $job): void
    {
        if (!$this->settings->shouldClearElementorCacheAfterJobs()) {
            return;
        }

        $jobType = (string) ($job['job_type'] ?? '');

        if ($jobType !== 'image_convert') {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);

        if ($jobId <= 0) {
            return;
        }

        $transientKey = 'wpok_job_finalized_' . $jobId;

        if (get_transient($transientKey)) {
            return;
        }

        $this->elementorCache->clear();
        set_transient($transientKey, 1, DAY_IN_SECONDS);
    }
}
