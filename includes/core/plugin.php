<?php

namespace WPOptiKit\Core;

use WPOptiKit\Core\Admin\AdminController;
use WPOptiKit\Core\Admin\AdminPageRegistry;
use WPOptiKit\Core\Contracts\ModuleInterface;
use WPOptiKit\Core\Queue\JobRegistry;
use WPOptiKit\Core\Queue\JobRepository;
use WPOptiKit\Core\Queue\QueueRunner;
use WPOptiKit\Core\Rest\JobsController;
use WPOptiKit\Core\Storage\OptionStore;
use WPOptiKit\Core\Storage\SchemaManager;
use WPOptiKit\Core\Updater\GithubUpdater;
use WPOptiKit\Modules\Image\ImageModule;
use WPOptiKit\Modules\Support\PlannedModule;

final class Plugin
{
    /**
     * @var array<string, ModuleInterface>
     */
    private array $modules = array();

    private Container $container;
    private bool $booted = false;

    public function __construct(private readonly string $pluginFile)
    {
        $this->container = new Container();
        $this->registerServices();
        $this->registerModules();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        /** @var QueueRunner $queue */
        $queue = $this->container->get('queue_runner');
        $queue->setContainer($this->container);
        $queue->boot();

        /** @var JobsController $jobsController */
        $jobsController = $this->container->get('jobs_controller');
        $jobsController->boot();

        /** @var AdminPageRegistry $adminRegistry */
        $adminRegistry = $this->container->get('admin_registry');
        $this->registerCoreTabs($adminRegistry);

        foreach ($this->modules as $module) {
            $module->register();
            $module->register_admin($adminRegistry);
        }

        if (is_admin()) {
            /** @var GithubUpdater $updater */
            $updater = $this->container->get('updater');
            $updater->boot();

            /** @var AdminController $admin */
            $admin = $this->container->get('admin_controller');
            $admin->boot();
        }

        $this->booted = true;
    }

    public function activate(): void
    {
        /** @var SchemaManager $schema */
        $schema = $this->container->get('schema');
        $schema->activate();
    }

    private function registerServices(): void
    {
        $this->container->factory('options', static fn (Container $container) => new OptionStore());
        $this->container->factory(
            'schema',
            fn (Container $container) => new SchemaManager($container->get('options'))
        );
        $this->container->factory('job_registry', static fn (Container $container) => new JobRegistry());
        $this->container->factory('job_repository', static fn (Container $container) => new JobRepository($GLOBALS['wpdb']));
        $this->container->factory(
            'queue_runner',
            fn (Container $container) => new QueueRunner(
                $container->get('job_repository'),
                $container->get('job_registry')
            )
        );
        $this->container->factory(
            'jobs_controller',
            fn (Container $container) => new JobsController(
                $container->get('job_repository'),
                $container->get('job_registry'),
                $container->get('queue_runner')
            )
        );
        $this->container->factory('admin_registry', static fn (Container $container) => new AdminPageRegistry());
        $this->container->factory(
            'admin_controller',
            fn (Container $container) => new AdminController(
                $container->get('admin_registry'),
                $this->modules,
                $container->get('options'),
                $container->get('job_repository')
            )
        );
        $this->container->factory('updater', fn (Container $container) => new GithubUpdater($this->pluginFile));
    }

    private function registerModules(): void
    {
        $modules = array(
            new ImageModule($this->container),
            new PlannedModule('cache', 'Cache', 'Coordinate cache orchestration, purge flows, and delivery control from a dedicated workspace.'),
            new PlannedModule('assets', 'Assets', 'Prepare CSS, JavaScript, and frontend delivery optimization in one controlled surface.'),
            new PlannedModule('database', 'Database', 'Manage cleanup, diagnostics, and maintenance routines through a focused operations panel.'),
        );

        foreach ($modules as $module) {
            $this->modules[$module->get_id()] = $module;
        }
    }

    private function registerCoreTabs(AdminPageRegistry $registry): void
    {
        $registry->addTab(
            'overview',
            'Overview',
            static function (array $context): void {
                include WPOK_DIR . 'templates/tabs/overview.php';
            }
        );
    }
}
