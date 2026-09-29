<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 店舗にグループ区分（group_key）を追加。
 *
 * 店舗をグループで明確に分割し、他グループの顧客・イベント等が見えないようにする。
 *  - 岡山グループ（okayama）：岡山店・城東店・浜店（相互に閲覧可）
 *  - 福井グループ（fukui）  ：福井店
 *  - テスト店は group_key=null（通常運用から除外）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('group_key', 32)->nullable()->after('name');
        });

        // 既存店舗に名称からグループを割り当てる
        DB::table('shops')->where('name', 'like', '%福井%')->update(['group_key' => 'fukui']);
        DB::table('shops')->where(function ($q) {
            $q->where('name', 'like', '%岡山%')
                ->orWhere('name', 'like', '%城東%')
                ->orWhere('name', 'like', '%浜%');
        })->update(['group_key' => 'okayama']);
        // テスト店などは null のまま（通常運用から除外）
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('group_key');
        });
    }
};
