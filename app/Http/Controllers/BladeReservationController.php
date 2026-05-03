<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use App\Services\BladeLp\FormSchemaValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Blade テンプレ方式の LP 用予約コントローラ。
 *
 * 既存 EventReservationController は固定カラムベース（form_type ごと）の予約を扱う。
 * こちらは events.form_schema に基づいた動的フォームを扱い、
 * 全回答を event_reservations.form_data にまとめて保存する。
 * 検索・既存運用整合のため、name / email / phone は固定カラムにも複製する。
 */
class BladeReservationController extends Controller
{
    public function __construct(
        protected FormSchemaValidator $schemaValidator,
    ) {}

    public function store(Request $request, Event $event)
    {
        if (!$event->is_public || !$event->usesBladeLp()) {
            abort(404);
        }

        $schema = is_array($event->form_schema) ? $event->form_schema : [];
        if ($schema === []) {
            abort(422, 'このイベントにはフォーム定義（form_schema）が設定されていません。');
        }

        $rules = $this->schemaValidator->rulesFromSchema($schema);
        $attrs = $this->schemaValidator->attributesFromSchema($schema);

        $validator = Validator::make($request->all(), $rules, [], $attrs);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // 検索用に標準キー（name/email/phone）が含まれていれば複製する
        $name  = is_string($validated['name']  ?? null) ? $validated['name']  : null;
        $email = is_string($validated['email'] ?? null) ? $validated['email'] : null;
        $phone = is_string($validated['phone'] ?? null) ? $validated['phone'] : null;

        $reservation = EventReservation::create([
            'event_id'   => $event->id,
            'name'       => $name  ?? '',
            'email'      => $email ?? '',
            'phone'      => $phone ?? '',
            'form_data'  => $validated,
            'utm_source' => $request->input('utm_source')
                ?? $request->session()->get('event_utm_sources.'.$event->id),
        ]);

        $request->session()->put('blade_lp_form_data.'.$event->id, $validated);

        return redirect()->route('blade-lp.thanks', ['event' => $event->id])
            ->with('reservation_id', $reservation->id);
    }

    public function thanks(Request $request, Event $event)
    {
        if (!$event->is_public || !$event->usesBladeLp()) {
            abort(404);
        }

        $view = $event->bladeLpView().'_thanks';

        // 専用 thanks ビューが無ければ汎用 thanks にフォールバック
        $thanksView = view()->exists($view) ? $view : 'event.lp.thanks';

        return view($thanksView, [
            'event' => $event,
            'formData' => $request->session()->get('blade_lp_form_data.'.$event->id, []),
        ]);
    }
}
