<?php

namespace WPOptiKit\Core\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPOptiKit\Core\Queue\JobRegistry;
use WPOptiKit\Core\Queue\JobRepository;
use WPOptiKit\Core\Queue\QueueRunner;

final class JobsController
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobRegistry $registry,
        private readonly QueueRunner $runner
    ) {
    }

    public function boot(): void
    {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'wp-optikit/v1',
            '/jobs',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'listJobs'),
                    'permission_callback' => array($this, 'canManage'),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'createJob'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/jobs/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'getJob'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/jobs/(?P<id>\d+)/items',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'getJobItems'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/jobs/(?P<id>\d+)/cancel',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'cancelJob'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );

        register_rest_route(
            'wp-optikit/v1',
            '/jobs/clear-completed',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'clearCompletedJobs'),
                    'permission_callback' => array($this, 'canManage'),
                ),
            )
        );
    }

    public function listJobs(WP_REST_Request $request): WP_REST_Response
    {
        $limit = max(1, min(50, (int) $request->get_param('limit')));

        return new WP_REST_Response(
            array(
                'jobs' => $this->jobs->getRecentJobs($limit > 0 ? $limit : 8),
            )
        );
    }

    public function createJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $module  = sanitize_key((string) $request->get_param('module'));
        $jobType = sanitize_key((string) $request->get_param('job_type'));
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = array();
        }

        $handler = $this->registry->getHandler($module, $jobType);

        if ($handler === null) {
            return new WP_Error('wpok_invalid_job', 'Unsupported job request.', array('status' => 400));
        }

        $items = $handler->build_items($jobType, $payload);

        if (empty($items)) {
            return new WP_Error('wpok_empty_job', 'No valid items were selected for this job.', array('status' => 400));
        }

        try {
            $jobId = $this->jobs->createJob($module, $jobType, $payload, get_current_user_id());
            $this->jobs->insertItems($jobId, $items);
            $this->runner->schedule();
        } catch (\Throwable $exception) {
            return new WP_Error('wpok_job_create_failed', $exception->getMessage(), array('status' => 500));
        }

        return new WP_REST_Response(array('job' => $this->jobs->getJob($jobId)), 201);
    }

    public function getJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $this->runner->tick();
        $job = $this->jobs->getJob((int) $request['id']);

        if ($job === null) {
            return new WP_Error('wpok_job_not_found', 'Job not found.', array('status' => 404));
        }

        return new WP_REST_Response(array('job' => $job));
    }

    public function getJobItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $this->runner->tick();
        $job = $this->jobs->getJob((int) $request['id']);

        if ($job === null) {
            return new WP_Error('wpok_job_not_found', 'Job not found.', array('status' => 404));
        }

        return new WP_REST_Response(
            array(
                'job'   => $job,
                'items' => $this->jobs->getJobItems((int) $request['id']),
            )
        );
    }

    public function cancelJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $job = $this->jobs->getJob((int) $request['id']);

        if ($job === null) {
            return new WP_Error('wpok_job_not_found', 'Job not found.', array('status' => 404));
        }

        $this->jobs->cancelJob((int) $request['id']);

        return new WP_REST_Response(array('job' => $this->jobs->getJob((int) $request['id'])));
    }

    public function clearCompletedJobs(WP_REST_Request $request): WP_REST_Response
    {
        $deleted = $this->jobs->clearCompletedJobs();

        return new WP_REST_Response(
            array(
                'deleted' => $deleted,
                'jobs'    => $this->jobs->getRecentJobs(),
            )
        );
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
