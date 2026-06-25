<?php

namespace WPOptiKit\Core\Admin;

final class AdminPageRegistry
{
    /**
     * @var array<string, array{id:string, label:string, renderer:callable}>
     */
    private array $tabs = array();

    public function addTab(string $id, string $label, callable $renderer): void
    {
        $this->tabs[$id] = array(
            'id'       => $id,
            'label'    => $label,
            'renderer' => $renderer,
        );
    }

    public function all(): array
    {
        return $this->tabs;
    }

    public function get(string $id): ?array
    {
        return $this->tabs[$id] ?? null;
    }
}
