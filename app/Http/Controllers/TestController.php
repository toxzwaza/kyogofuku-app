<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PhotoSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

//
class TestController extends Controller
{
    public function index()
    {
        $photo_slots = PhotoSlot::all();

        foreach ($photo_slots as $photo_slot) {
            // shop_idを決定（234以下は1、それ以上は2）
            $shop_id = $photo_slot->id <= 234 ? 1 : 2;

            // 既に中間テーブルに存在するかチェック
            $exists = DB::table('photo_slot_shop')
                ->where('photo_slot_id', $photo_slot->id)
                ->where('shop_id', $shop_id)
                ->exists();

            // 存在しない場合のみ追加
            if (!$exists) {
                DB::table('photo_slot_shop')->insert([
                    'photo_slot_id' => $photo_slot->id,
                    'shop_id' => $shop_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
