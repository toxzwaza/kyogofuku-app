<?php

namespace App\Services\PhotoSlot;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 前撮りの二重登録で止めたとき。
 * 画面の確認（「この方は 8:30 に登録済みです。9:00 に変更しますか？」）に使えるよう、今の登録を持たせる。
 *
 * reason：
 *  - confirm      今の登録が1件。replace_photo_slot_id にその ID を付けて送り直せば移す
 *  - same         もう同じ時刻（または日時未定）で登録済み。移すものはない
 *  - multiple     すでに2件以上ある（前からの二重登録）。自動では選ばない
 *  - other_group  ほかの店舗グループの登録。見えない枠は動かさない
 *
 * Inertia の画面ではふつうの入力エラー（errors.duplicate）として返り、JSON では 409 で中身を付けて返す。
 */
class DuplicateBookingException extends ValidationException
{
    /**
     * @param  array<int, array{id:int, studio:?string, date:?string, time:?string, undecided:bool, label:string}>  $existing
     */
    public function __construct(public string $reason, string $message, public array $existing = [])
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('duplicate', $message);
        parent::__construct($validator);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null; // ふつうの入力エラーとして戻す
        }

        return response()->json([
            'message' => $this->errors()['duplicate'][0] ?? '',
            'errors' => $this->errors(),
            'duplicate' => [
                'reason' => $this->reason,
                'existing' => $this->existing,
            ],
        ], 409);
    }
}
