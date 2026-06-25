<?php

namespace WPOptiKit\Core\Queue;

use RuntimeException;
use wpdb;

final class JobRepository
{
    private string $jobsTable;
    private string $itemsTable;

    public function __construct(private readonly wpdb $wpdb)
    {
        $this->jobsTable  = $wpdb->prefix . 'wpok_jobs';
        $this->itemsTable = $wpdb->prefix . 'wpok_job_items';
    }

    public function createJob(string $module, string $jobType, array $payload, int $createdBy): int
    {
        $now = current_time('mysql', true);

        $this->wpdb->insert(
            $this->jobsTable,
            array(
                'module'          => $module,
                'job_type'        => $jobType,
                'status'          => 'pending',
                'payload_json'    => wp_json_encode($payload),
                'total_items'     => 0,
                'processed_items' => 0,
                'failed_items'    => 0,
                'created_by'      => $createdBy,
                'created_at'      => $now,
                'updated_at'      => $now,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if ((int) $this->wpdb->insert_id <= 0) {
            throw new RuntimeException('Unable to create queue job.');
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function insertItems(int $jobId, array $items): void
    {
        $now = current_time('mysql', true);

        foreach ($items as $item) {
            $inserted = $this->wpdb->insert(
                $this->itemsTable,
                array(
                    'job_id'        => $jobId,
                    'object_type'   => (string) ($item['object_type'] ?? 'attachment'),
                    'object_id'     => (int) ($item['object_id'] ?? 0),
                    'source_path'   => (string) ($item['source_path'] ?? ''),
                    'status'        => 'pending',
                    'attempt_count' => 0,
                    'result_json'   => null,
                    'last_error'    => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ),
                array('%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );

            if ($inserted !== 1) {
                throw new RuntimeException('Unable to insert queue job items.');
            }
        }

        $this->refreshJob($jobId);
    }

    public function getJob(int $jobId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->jobsTable} WHERE id = %d", $jobId),
            ARRAY_A
        );

        return $row ? $this->normalizeJob($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getJobItems(int $jobId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->itemsTable} WHERE job_id = %d ORDER BY id ASC", $jobId),
            ARRAY_A
        );

        return array_map(array($this, 'normalizeItem'), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentJobs(int $limit = 8): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->jobsTable} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        );

        return array_map(array($this, 'normalizeJob'), $rows);
    }

    public function clearCompletedJobs(): int
    {
        $ids = $this->wpdb->get_col(
            "SELECT id FROM {$this->jobsTable} WHERE status NOT IN ('pending', 'processing', 'cancelling')"
        );

        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));

        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->itemsTable} WHERE job_id IN ({$placeholders})",
                ...$ids
            )
        );

        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->jobsTable} WHERE id IN ({$placeholders})",
                ...$ids
            )
        );

        return max(0, (int) $deleted);
    }

    public function cancelJob(int $jobId): void
    {
        $now = current_time('mysql', true);

        $this->wpdb->update(
            $this->jobsTable,
            array(
                'status'     => 'cancelling',
                'updated_at' => $now,
            ),
            array(
                'id' => $jobId,
            ),
            array('%s', '%s'),
            array('%d')
        );

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->itemsTable} SET status = 'cancelled', updated_at = %s
                 WHERE job_id = %d AND status = 'pending'",
                $now,
                $jobId
            )
        );

        $this->refreshJob($jobId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claimPendingItems(int $limit): array
    {
        $now  = current_time('mysql', true);
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT items.*, jobs.module, jobs.job_type, jobs.payload_json
                 FROM {$this->itemsTable} items
                 INNER JOIN {$this->jobsTable} jobs ON jobs.id = items.job_id
                 WHERE items.status = 'pending' AND jobs.status IN ('pending', 'processing')
                 ORDER BY items.id ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $claimed = array();

        foreach ($rows as $row) {
            $updated = $this->wpdb->update(
                $this->itemsTable,
                array(
                    'status'        => 'processing',
                    'attempt_count' => ((int) $row['attempt_count']) + 1,
                    'updated_at'    => $now,
                ),
                array(
                    'id'     => (int) $row['id'],
                    'status' => 'pending',
                ),
                array('%s', '%d', '%s'),
                array('%d', '%s')
            );

            if ($updated !== 1) {
                continue;
            }

            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->jobsTable} SET status = 'processing', updated_at = %s WHERE id = %d AND status = 'pending'",
                    $now,
                    (int) $row['job_id']
                )
            );

            $row['status']        = 'processing';
            $row['attempt_count'] = ((int) $row['attempt_count']) + 1;
            $claimed[]            = $this->normalizeClaimedItem($row);
        }

        return $claimed;
    }

    public function claimNextItem(): ?array
    {
        $claimed = $this->claimPendingItems(1);

        return $claimed[0] ?? null;
    }

    public function completeItem(int $jobId, int $itemId, string $status, array $result = array(), string $error = ''): void
    {
        $this->wpdb->update(
            $this->itemsTable,
            array(
                'status'      => $status,
                'result_json' => !empty($result) ? wp_json_encode($result) : null,
                'last_error'  => $error !== '' ? $error : null,
                'updated_at'  => current_time('mysql', true),
            ),
            array('id' => $itemId),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        $this->refreshJob($jobId);
    }

    public function releaseItemForRetry(int $jobId, int $itemId, string $error = ''): void
    {
        $this->wpdb->update(
            $this->itemsTable,
            array(
                'status'      => 'pending',
                'last_error'  => $error !== '' ? $error : null,
                'result_json' => null,
                'updated_at'  => current_time('mysql', true),
            ),
            array('id' => $itemId),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        $this->refreshJob($jobId);
    }

    public function refreshJob(int $jobId): void
    {
        $currentJob = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT status, processed_items FROM {$this->jobsTable} WHERE id = %d", $jobId),
            ARRAY_A
        );

        if (!is_array($currentJob)) {
            return;
        }

        $currentStatus    = (string) ($currentJob['status'] ?? '');
        $frozenProcessed  = (int) ($currentJob['processed_items'] ?? 0);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT status, COUNT(*) AS count_total FROM {$this->itemsTable} WHERE job_id = %d GROUP BY status",
                $jobId
            ),
            ARRAY_A
        );

        $counts = array(
            'pending'    => 0,
            'processing' => 0,
            'succeeded'  => 0,
            'failed'     => 0,
            'skipped'    => 0,
            'cancelled'  => 0,
        );

        $total = 0;

        foreach ($rows as $row) {
            $status         = (string) $row['status'];
            $count          = (int) $row['count_total'];
            $counts[$status] = $count;
            $total         += $count;
        }

        $processed = $counts['succeeded'] + $counts['failed'] + $counts['skipped'];

        if ($total === 0) {
            $jobStatus = 'pending';
        } elseif ($currentStatus === 'cancelling') {
            $jobStatus = ($counts['pending'] > 0 || $counts['processing'] > 0) ? 'cancelling' : 'cancelled';
        } elseif ($counts['processing'] > 0) {
            $jobStatus = 'processing';
        } elseif ($counts['pending'] > 0) {
            $jobStatus = $processed > 0 ? 'processing' : 'pending';
        } elseif ($counts['failed'] > 0) {
            $jobStatus = 'failed';
        } elseif ($counts['cancelled'] === $total) {
            $jobStatus = 'cancelled';
        } else {
            $jobStatus = 'succeeded';
        }

        if ($currentStatus === 'cancelling' || $currentStatus === 'cancelled') {
            $processed = $frozenProcessed;
        }

        $this->wpdb->update(
            $this->jobsTable,
            array(
                'status'          => $jobStatus,
                'total_items'     => $total,
                'processed_items' => $processed,
                'failed_items'    => $counts['failed'],
                'updated_at'      => current_time('mysql', true),
            ),
            array('id' => $jobId),
            array('%s', '%d', '%d', '%d', '%s'),
            array('%d')
        );
    }

    public function hasRunnableJobs(): bool
    {
        $count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->itemsTable} WHERE status = 'pending'"
        );

        return $count > 0;
    }

    private function normalizeJob(array $row): array
    {
        return array(
            'id'              => (int) $row['id'],
            'module'          => (string) $row['module'],
            'job_type'        => (string) $row['job_type'],
            'status'          => (string) $row['status'],
            'payload'         => $row['payload_json'] ? (array) json_decode((string) $row['payload_json'], true) : array(),
            'total_items'     => (int) $row['total_items'],
            'processed_items' => (int) $row['processed_items'],
            'failed_items'    => (int) $row['failed_items'],
            'created_by'      => (int) $row['created_by'],
            'created_at'      => (string) $row['created_at'],
            'updated_at'      => (string) $row['updated_at'],
        );
    }

    private function normalizeItem(array $row): array
    {
        return array(
            'id'            => (int) $row['id'],
            'job_id'        => (int) $row['job_id'],
            'object_type'   => (string) $row['object_type'],
            'object_id'     => (int) $row['object_id'],
            'source_path'   => (string) $row['source_path'],
            'status'        => (string) $row['status'],
            'attempt_count' => (int) $row['attempt_count'],
            'result'        => $row['result_json'] ? (array) json_decode((string) $row['result_json'], true) : array(),
            'last_error'    => (string) ($row['last_error'] ?? ''),
            'created_at'    => (string) $row['created_at'],
            'updated_at'    => (string) $row['updated_at'],
        );
    }

    private function normalizeClaimedItem(array $row): array
    {
        $normalized             = $this->normalizeItem($row);
        $normalized['module']   = (string) $row['module'];
        $normalized['job_type'] = (string) $row['job_type'];
        $normalized['payload']  = $row['payload_json'] ? (array) json_decode((string) $row['payload_json'], true) : array();

        return $normalized;
    }
}
