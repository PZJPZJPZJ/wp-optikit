<?php

namespace WPOptiKit\Modules\Image;

use WPOptiKit\Core\Admin\AdminPageRegistry;
use WPOptiKit\Core\Container;
use WPOptiKit\Core\Contracts\ModuleInterface;
use WPOptiKit\Core\Queue\JobRegistry;
use WPOptiKit\Core\Storage\OptionStore;

final class ImageModule implements ModuleInterface
{
    private ImageSettings $settings;
    private ImageProcessor $processor;
    private ImageScanner $scanner;
    private ThumbnailMissingScanner $thumbnailScanner;
    private ImageJobHandler $jobHandler;
    private ImageRestController $restController;
    private ImageJobFinalizer $jobFinalizer;

    public function __construct(Container $container)
    {
        /** @var OptionStore $options */
        $options = $container->get('options');

        $this->settings       = new ImageSettings($options);
        $this->processor      = new ImageProcessor($this->settings);
        $this->scanner        = new ImageScanner($this->settings);
        $this->thumbnailScanner = new ThumbnailMissingScanner($GLOBALS['wpdb']);
        $this->jobHandler     = new ImageJobHandler($this->processor);
        $this->restController = new ImageRestController($this->scanner, $this->thumbnailScanner);
        $this->jobFinalizer   = new ImageJobFinalizer($this->settings, new ElementorCacheBridge());

        /** @var JobRegistry $jobRegistry */
        $jobRegistry = $container->get('job_registry');
        $jobRegistry->registerHandler($this->jobHandler);
        $container->set('image_job_finalizer', $this->jobFinalizer);
    }

    public function get_id(): string
    {
        return 'image';
    }

    public function get_label(): string
    {
        return __('Images', 'wp-optikit');
    }

    public function is_enabled(): bool
    {
        return true;
    }

    public function register(): void
    {
        $this->settings->register();
        $this->restController->boot();

        add_filter('wp_handle_upload', array($this->processor, 'handleUpload'));
        add_filter('wp_generate_attachment_metadata', array($this->processor, 'handleAttachmentMetadata'), 10, 2);
    }

    public function register_admin(AdminPageRegistry $admin): void
    {
        $settings  = $this->settings;

        $admin->addTab(
            'images',
            __('Images', 'wp-optikit'),
            static function (array $context) use ($settings): void {
                $imageSettings    = $settings->get();
                $engine           = $settings->getEngine();
                $gdAvailable      = extension_loaded('gd');
                $imagickAvailable = extension_loaded('imagick');

                include WPOK_DIR . 'templates/tabs/images.php';
            }
        );
    }
}
