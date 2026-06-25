<?php

namespace WPOptiKit\Core\Contracts;

interface JobHandlerInterface
{
    public function get_module(): string;

    public function supports_job_type(string $jobType): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build_items(string $jobType, array $payload): array;

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $item
     *
     * @return array{status:string, result?:array<string, mixed>, error?:string}
     */
    public function process_item(string $jobType, array $job, array $item): array;
}
