<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

/**
 * 店舗一覧の公開API（iPad 写真アップロードアプリの端末登録画面で使用）
 *
 * 端末登録が可能な店舗（有効かつ端末登録パスワード設定済み）のみを返す。
 * 公開情報は id と店舗名だけに限定する。
 */
class PublicShopController extends Controller
{
    public function index(): JsonResponse
    {
        $shops = Shop::query()
            ->where('is_active', true)
            ->whereNotNull('device_password')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Shop $shop) => ['id' => $shop->id, 'name' => $shop->name]);

        return response()->json(['shops' => $shops], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
