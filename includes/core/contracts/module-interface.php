<?php

namespace WPOptiKit\Core\Contracts;

use WPOptiKit\Core\Admin\AdminPageRegistry;

interface ModuleInterface
{
    public function get_id(): string;

    public function get_label(): string;

    public function is_enabled(): bool;

    public function register(): void;

    public function register_admin(AdminPageRegistry $admin): void;
}
