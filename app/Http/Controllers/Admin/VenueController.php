<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VenueController extends Controller
{
    /**
     * 会場を保存
     */
    public function store(Request $request, Event $event)
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
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

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
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

        $venue->update($validated);

        return redirect()->back()
            ->with('success', '会場を更新しました。');
    }

    /**
     * 会場を削除
     */
    public function destroy(Event $event, Venue $venue)
    {
        $event->venues()->detach($venue->id);
        
        // 他のイベントで使用されていない場合は削除
        if ($venue->events()->count() === 0) {
            $venue->delete();
        }

        return redirect()->back()
            ->with('success', '会場を削除しました。');
    }
}

