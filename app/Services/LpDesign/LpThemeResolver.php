<?php

namespace App\Services\LpDesign;

use App\Models\Event;

class LpThemeResolver
{
    /**
     * 既定トークンとイベントの上書きをマージし、style オブジェクト用にフラット化する
     *
     * @return array<string, string>
     */
    public function resolveCssVarsForEvent(Event $event): array
    {
        $slug = $event->activeLpDesignSlug();
        if (!$slug) {
            return [];
        }

        $defaults = config("lp_designs.templates.{$slug}.default_tokens", []);
        if (!is_array($defaults)) {
            $defaults = [];
        }

        $override = $event->lp_theme_tokens;
        if (!is_array($override)) {
            $override = [];
        }

        $allowed = config('lp_designs.allowed_token_keys', []);
        if (!is_array($allowed)) {
            $allowed = [];
        }
        $allowedSet = array_flip($allowed);

        $merged = $defaults;
        foreach ($override as $key => $value) {
            if (!is_string($key) || !isset($allowedSet[$key])) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $merged[$key] = (string) $value;
        }

        return $merged;
    }
}
