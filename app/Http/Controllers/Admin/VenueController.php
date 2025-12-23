<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VenueController extends Controller
{
    /**
     * 会場一覧を表示
     */
    public function index()
    {
        $venues = Venue::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Admin/Venue/Index', [
            'venues' => $venues,
        ]);
    }

    /**
     * 会場追加フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/Venue/Create');
    }

    /**
     * 会場詳細を表示
     */
    public function show(Venue $venue)
    {
        $venue->load('events');

        return Inertia::render('Admin/Venue/Show', [
            'venue' => $venue,
        ]);
    }

    /**
     * 会場編集フォームを表示
     */
    public function edit(Venue $venue)
    {
        return Inertia::render('Admin/Venue/Edit', [
            'venue' => $venue,
        ]);
    }

    /**
     * 会場を保存（新規作成）
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('venues', 'public');
        }

        Venue::create($validated);

        return redirect()->route('admin.venues.index')
            ->with('success', '会場を追加しました。');
    }

    /**
     * 会場を保存（イベント単位）
     */
    public function storeForEvent(Request $request, Event $event)
    {
        // 既存会場を選択した場合
        if ($request->has('venue_id') && $request->venue_id) {
            $venue = Venue::findOrFail($request->venue_id);
            
            // 既に関連付けられていない場合のみ追加
            if (!$event->venues()->where('venue_id', $venue->id)->exists()) {
                $event->venues()->attach($venue->id);
            }
            
            return redirect()->back()
                ->with('success', '会場を追加しました。');
        }
        
        // 新規会場を作成する場合
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('venues', 'public');
        }

        $venue = Venue::create($validated);
        
        // イベントと関連付け
        $event->venues()->attach($venue->id);

        return redirect()->back()
            ->with('success', '会場を追加しました。');
    }

    /**
     * 会場を更新
     */
    public function update(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : $venue->is_active;

        if ($request->hasFile('image')) {
            // 古い画像を削除
            if ($venue->image && Storage::disk('public')->exists($venue->image)) {
                Storage::disk('public')->delete($venue->image);
            }
            $validated['image'] = $request->file('image')->store('venues', 'public');
        } elseif ($request->has('remove_image')) {
            // 画像削除リクエスト
            if ($venue->image && Storage::disk('public')->exists($venue->image)) {
                Storage::disk('public')->delete($venue->image);
            }
            $validated['image'] = null;
        }

        $venue->update($validated);

        // イベント単位の更新の場合は戻る、独立した更新の場合は一覧に戻る
        if ($request->has('_redirect_to_index')) {
            return redirect()->route('admin.venues.index')
                ->with('success', '会場を更新しました。');
        }

        return redirect()->back()
            ->with('success', '会場を更新しました。');
    }

    /**
     * 会場を削除（イベント単位）
     */
    public function destroyFromEvent(Event $event, Venue $venue)
    {
        $event->venues()->detach($venue->id);
        
        // 他のイベントで使用されていない場合は削除
        if ($venue->events()->count() === 0) {
            $venue->delete();
        }

        return redirect()->back()
            ->with('success', '会場を削除しました。');
    }

    /**
     * 会場を削除
     */
    public function destroy(Venue $venue)
    {
        // 関連するイベントがある場合は削除不可にする
        if ($venue->events()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => '関連するイベントが存在するため削除できません。']);
        }

        $venue->delete();

        return redirect()->route('admin.venues.index')
            ->with('success', '会場を削除しました。');
    }
}

