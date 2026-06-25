<?php

namespace WPOptiKit\Core\Queue;

use WPOptiKit\Core\Container;

final class QueueRunner
{
    private const LOCK_KEY = 'wpok_queue_runner_lock';

    private bool $booted = false;
    private int $batchSize = 1;
    private ?Container $container = null;

    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobRegistry $registry
    ) {
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        add_action('wpok_process_queue', array($this, 'process'));
        $this->booted = true;
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function schedule(): void
    {
        if (!wp_next_scheduled('wpok_process_queue')) {
            wp_schedule_single_event(time() + 1, 'wpok_process_queue');
        }
    }

    public function process(): void
    {
        $this->runBatch();
    }

    public function tick(): void
    {
        if (!$this->jobs->hasRunnableJobs()) {
            return;
        }

        $this->runBatch();
    }

    private function runBatch(): void
    {
        if (get_transient(self::LOCK_KEY)) {
            return;
        }

        set_transient(self::LOCK_KEY, 1, 30);

        try {
            for ($index = 0; $index < $this->batchSize; $index++) {
                $item = $this->jobs->claimNextItem();

                if ($item === null) {
                    break;
                }

                $this->processClaimedItem($item);
            }
        } finally {
            delete_transient(self::LOCK_KEY);
        }

        if ($this->jobs->hasRunnableJobs()) {
            $this->schedule();
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function processClaimedItem(array $item): void
    {
            $handler = $this->registry->getHandler((string) $item['module'], (string) $item['job_type']);

            if ($handler === null) {
                $this->jobs->completeItem(
                    (int) $item['job_id'],
                    (int) $item['id'],
                    'failed',
                    array(),
                    'No handler registered for this job type.'
                );
                return;
            }

            $jobContext = array(
                'job_id'   => (int) $item['job_id'],
                'module'   => (string) $item['module'],
                'job_type' => (string) $item['job_type'],
                'payload'  => (array) $item['payload'],
            );

            $result = $handler->process_item((string) $item['job_type'], $jobContext, $item);
            $status = (string) ($result['status'] ?? 'failed');
            $data   = (array) ($result['result'] ?? array());
            $error  = (string) ($result['error'] ?? '');

            if ($status === 'failed' && (int) $item['attempt_count'] < 2) {
                $this->jobs->releaseItemForRetry((int) $item['job_id'], (int) $item['id'], $error);
                return;
            }

            $this->jobs->completeItem((int) $item['job_id'], (int) $item['id'], $status, $data, $error);

            $job = $this->jobs->getJob((int) $item['job_id']);
            if ($job !== null) {
                $this->runFinalizer($job);
            }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function runFinalizer(array $job): void
    {
        if ($this->container === null) {
            return;
        }

        $status = (string) ($job['status'] ?? '');

        if ($status === 'pending' || $status === 'processing') {
            return;
        }

        try {
            $finalizer = $this->container->get('image_job_finalizer');
        } catch (\Throwable $exception) {
            return;
        }

        if (is_object($finalizer) && method_exists($finalizer, 'handleCompletedJob')) {
            $finalizer->handleCompletedJob($job);
        }
    }
}
