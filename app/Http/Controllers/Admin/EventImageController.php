<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EventImageController extends Controller
{
    /**
     * イベント画像一覧を表示
     */
    public function index(Event $event)
    {
        $images = $event->images()->orderBy('sort_order')->get();

        return Inertia::render('Admin/EventImage/Index', [
            'event' => $event,
            'images' => $images,
        ]);
    }

    /**
     * イベント画像追加フォームを表示
     */
    public function create(Event $event)
    {
        return Inertia::render('Admin/EventImage/Create', [
            'event' => $event,
        ]);
    }

    /**
     * イベント画像を保存
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            'alt' => 'nullable|string|max:255',
        ]);

        $maxSortOrder = $event->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store('events/' . $event->id, 'public');
            
            EventImage::create([
                'event_id' => $event->id,
                'path' => $path,
                'alt' => $request->alt ?? null,
                'sort_order' => $maxSortOrder + $index + 1,
            ]);
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', '画像を追加しました。');
    }

    /**
     * イベント画像を削除
     */
    public function destroy(EventImage $image)
    {
        $eventId = $image->event_id;
        
        // ストレージからファイルを削除
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return redirect()->route('admin.events.images.index', $eventId)
            ->with('success', '画像を削除しました。');
    }

    /**
     * ソート順を更新
     */
    public function updateSortOrder(Request $request, Event $event)
    {
        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:event_images,id',
        ]);

        foreach ($validated['image_ids'] as $index => $imageId) {
            EventImage::where('id', $imageId)
                ->where('event_id', $event->id)
                ->update(['sort_order' => $index + 1]);
        }

        return redirect()->route('admin.events.images.index', $event->id)
            ->with('success', 'ソート順を更新しました。');
    }
}

