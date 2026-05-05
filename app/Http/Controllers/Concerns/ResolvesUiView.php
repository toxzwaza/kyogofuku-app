<?php

namespace App\Http\Controllers\Concerns;

trait ResolvesUiView
{
    protected function viewFor(string $component): string
    {
        if (request()->cookie('ui_version') !== 'legacy') {
            return $component;
        }

        $legacyComponent = $component.'Legacy';
        $relativePath = 'js/Pages/'.$legacyComponent.'.vue';

        return file_exists(resource_path($relativePath))
            ? $legacyComponent
            : $component;
    }
}
