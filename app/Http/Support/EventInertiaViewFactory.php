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
        protected EventSeoMetaBuilder $seoBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $extraProps
     */
    public function showResponse(Event $event, array $extraProps = []): Response
    {
        $payload = array_merge($this->pageService->buildShowPayload($event), $extraProps);
        $lpSeo = $this->seoBuilder->build($event);
        // LCP対策: FV画像（1枚目）をpreload
        $firstImage = $payload['images'][0] ?? null;
        $lpSeo['preloadImage'] = $firstImage['webp_path'] ?? $firstImage['path'] ?? null;
        $slug = $event->activeLpDesignSlug();
        if ($slug) {
            $payload['lpThemeCssVars'] = $this->themeResolver->resolveCssVarsForEvent($event);
            $component = config("lp_designs.templates.{$slug}.inertia_show");

            return Inertia::render($component, $payload)->withViewData(['lpSeo' => $lpSeo]);
        }

        return Inertia::render('Event/Show', $payload)->withViewData(['lpSeo' => $lpSeo]);
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
