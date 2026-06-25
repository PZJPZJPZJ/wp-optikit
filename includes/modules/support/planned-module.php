<?php

namespace WPOptiKit\Modules\Support;

use WPOptiKit\Core\Admin\AdminPageRegistry;
use WPOptiKit\Core\Contracts\ModuleInterface;

final class PlannedModule implements ModuleInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $description
    ) {
    }

    public function get_id(): string
    {
        return $this->id;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function is_enabled(): bool
    {
        return false;
    }

    public function register(): void
    {
    }

    public function register_admin(AdminPageRegistry $admin): void
    {
        $label       = $this->label;
        $description = $this->description;

        $admin->addTab(
            $this->id,
            $this->label,
            static function (array $context) use ($label, $description): void {
                $plannedTitle       = $label;
                $plannedDescription = $description;
                include WPOK_DIR . 'templates/tabs/planned.php';
            }
        );
    }
}
