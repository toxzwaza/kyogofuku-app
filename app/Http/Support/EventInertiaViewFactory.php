<?php

namespace App\Http\Support;

use App\Models\Event;
use App\Services\Event\EventPublicPageService;
use App\Services\LpDesign\LpThemeResolver;
use Inertia\Inertia;
use Inertia\Response;

class EventInertiaViewFactory
{
    public function __construct(
        protected EventPublicPageService $pageService,
        protected LpThemeResolver $themeResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $extraProps
     */
    public function showResponse(Event $event, array $extraProps = []): Response
    {
        $payload = array_merge($this->pageService->buildShowPayload($event), $extraProps);
        $slug = $event->activeLpDesignSlug();
        if ($slug) {
            $payload['lpThemeCssVars'] = $this->themeResolver->resolveCssVarsForEvent($event);
            $component = config("lp_designs.templates.{$slug}.inertia_show");

            return Inertia::render($component, $payload);
        }

        return Inertia::render('Event/Show', $payload);
    }

    /**
     * @param  array<string, mixed>  $extraProps
     */
    public function reserveResponse(Event $event, array $extraProps = []): Response
    {
        $slug = $event->activeLpDesignSlug();
        if (!$slug) {
            abort(404);
        }

        $payload = array_merge($this->pageService->buildShowPayload($event), $extraProps);
        $payload['lpThemeCssVars'] = $this->themeResolver->resolveCssVarsForEvent($event);
        $component = config("lp_designs.templates.{$slug}.inertia_reserve");

        return Inertia::render($component, $payload);
    }
}
