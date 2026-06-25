<?php

namespace WPOptiKit\Core\Queue;

use WPOptiKit\Core\Contracts\JobHandlerInterface;

final class JobRegistry
{
    /**
     * @var array<string, JobHandlerInterface>
     */
    private array $handlers = array();

    public function registerHandler(JobHandlerInterface $handler): void
    {
        $this->handlers[$handler->get_module()] = $handler;
    }

    public function getHandler(string $module, ?string $jobType = null): ?JobHandlerInterface
    {
        $handler = $this->handlers[$module] ?? null;

        if ($handler === null) {
            return null;
        }

        if ($jobType !== null && !$handler->supports_job_type($jobType)) {
            return null;
        }

        return $handler;
    }
}
