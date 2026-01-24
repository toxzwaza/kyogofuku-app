<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeslotTemplate;
use App\Models\TimeslotTemplateSlot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimeslotTemplateController extends Controller
{
    /**
     * テンプレートグループ一覧を表示
     */
    public function index()
    {
        $templates = TimeslotTemplate::with('slots')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/TimeslotTemplate/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * テンプレートグループ作成フォームを表示
     */
    public function create()
    {
        return Inertia::render('Admin/TimeslotTemplate/Create');
    }

    /**
     * テンプレートグループを保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slots' => 'required|array|min:1',
            'slots.*.hour' => 'required|integer|min:0|max:23',
            'slots.*.minute' => 'required|integer|min:0|max:59',
            'slots.*.capacity' => 'required|integer|min:1',
        ]);

        $template = TimeslotTemplate::create([
            'name' => $validated['name'],
        ]);

        foreach ($validated['slots'] as $slotData) {
            TimeslotTemplateSlot::create([
                'timeslot_template_id' => $template->id,
                'hour' => $slotData['hour'],
                'minute' => $slotData['minute'],
                'capacity' => $slotData['capacity'],
            ]);
        }

        return redirect()->route('admin.timeslot-templates.index')
            ->with('success', 'テンプレートグループを作成しました。');
    }

    /**
     * テンプレートグループ編集フォームを表示
     */
    public function edit(TimeslotTemplate $timeslotTemplate)
    {
        $timeslotTemplate->load('slots');

        return Inertia::render('Admin/TimeslotTemplate/Edit', [
            'template' => $timeslotTemplate,
        ]);
    }

    /**
     * テンプレートグループを更新
     */
    public function update(Request $request, TimeslotTemplate $timeslotTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slots' => 'required|array|min:1',
            'slots.*.hour' => 'required|integer|min:0|max:23',
            'slots.*.minute' => 'required|integer|min:0|max:59',
            'slots.*.capacity' => 'required|integer|min:1',
        ]);

        $timeslotTemplate->update([
            'name' => $validated['name'],
        ]);

        // 既存のスロットを削除
        $timeslotTemplate->slots()->delete();

        // 新しいスロットを作成
        foreach ($validated['slots'] as $slotData) {
            TimeslotTemplateSlot::create([
                'timeslot_template_id' => $timeslotTemplate->id,
                'hour' => $slotData['hour'],
                'minute' => $slotData['minute'],
                'capacity' => $slotData['capacity'],
            ]);
        }

        return redirect()->route('admin.timeslot-templates.index')
            ->with('success', 'テンプレートグループを更新しました。');
    }

    /**
     * テンプレートグループを削除
     */
    public function destroy(TimeslotTemplate $timeslotTemplate)
    {
        $timeslotTemplate->delete();

        return redirect()->route('admin.timeslot-templates.index')
            ->with('success', 'テンプレートグループを削除しました。');
    }
}
