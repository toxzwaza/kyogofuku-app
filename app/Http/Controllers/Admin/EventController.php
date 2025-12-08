<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Shop;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * イベント一覧を表示
     */
    public function index()
    {
        $events = Event::with(['shops'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Event/Index', [
            'events' => $events,
        ]);
    }

    /**
     * イベント追加フォームを表示
     */
    public function create()
    {
        $shops = Shop::where('is_active', true)->get();
        $venues = Venue::where('is_active', true)->get();

        return Inertia::render('Admin/Event/Create', [
            'shops' => $shops,
            'venues' => $venues,
        ]);
    }

    /**
     * イベントを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type' => 'required|in:reservation,document,contact',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_public' => 'boolean',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
            'venue_ids' => 'nullable|array',
            'venue_ids.*' => 'exists:venues,id',
            'new_venue_name' => 'nullable|string|max:255',
            'new_venue_description' => 'nullable|string',
            'new_venue_address' => 'nullable|string|max:255',
            'new_venue_phone' => 'nullable|string|max:255',
        ]);

        // date型なのでそのまま使用（datetime-localの場合はT以降を削除）
        if ($request->has('start_at') && $request->start_at) {
            $validated['start_at'] = explode('T', $request->start_at)[0];
        }
        if ($request->has('end_at') && $request->end_at) {
            $validated['end_at'] = explode('T', $request->end_at)[0];
        }

        // slugを生成（タイトルから）
        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        $validated['slug'] = $slug;

        $validated['is_public'] = $request->has('is_public') ? $request->is_public : true;

        $event = Event::create($validated);

        // 店舗を関連付け
        if ($request->has('shop_ids')) {
            $event->shops()->attach($request->shop_ids);
        }

        // 予約フォームの場合、会場を関連付け
        if ($event->form_type === 'reservation') {
            // 既存の会場を関連付け
            if ($request->has('venue_ids')) {
                $event->venues()->attach($request->venue_ids);
            }
            
            // 新規会場を作成して関連付け
            if ($request->has('new_venue_name') && $request->new_venue_name) {
                $venue = Venue::create([
                    'name' => $request->new_venue_name,
                    'description' => $request->new_venue_description,
                    'address' => $request->new_venue_address,
                    'phone' => $request->new_venue_phone,
                    'is_active' => true,
                ]);
                $event->venues()->attach($venue->id);
            }
        }

        return redirect()->route('admin.events.index')
            ->with('success', 'イベントを追加しました。');
    }

    /**
     * イベント詳細を表示
     */
    public function show(Event $event)
    {
        $event->load(['shops', 'images', 'timeslots', 'reservations', 'venues']);
        $allVenues = Venue::where('is_active', true)->get();
        $allShops = Shop::where('is_active', true)->get();

        return Inertia::render('Admin/Event/Show', [
            'event' => $event,
            'allVenues' => $allVenues,
            'allShops' => $allShops,
        ]);
    }

    /**
     * イベントを更新
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type' => 'required|in:reservation,document,contact',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_public' => 'boolean',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'exists:shops,id',
        ]);

        // date型なのでそのまま使用（datetime-localの場合はT以降を削除）
        if ($request->has('start_at') && $request->start_at) {
            $validated['start_at'] = explode('T', $request->start_at)[0];
        }
        if ($request->has('end_at') && $request->end_at) {
            $validated['end_at'] = explode('T', $request->end_at)[0];
        }

        // slugを更新（タイトルが変更された場合のみ）
        if ($event->title !== $validated['title']) {
            $baseSlug = Str::slug($validated['title']);
            $slug = $baseSlug;
            $counter = 1;
            while (Event::where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $validated['is_public'] = $request->has('is_public') ? $request->is_public : true;

        $event->update($validated);

        // 店舗の関連付けを更新
        if ($request->has('shop_ids')) {
            $event->shops()->sync($request->shop_ids);
        } else {
            $event->shops()->detach();
        }

        return redirect()->route('admin.events.show', $event->id)
            ->with('success', 'イベントを更新しました。');
    }
}

