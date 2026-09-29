<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 初期システム管理者の設定。
 *
 * スタッフ・権限管理をシステム管理者(system_admin)専用にしたため、
 * ロックアウトを防ぐため少なくとも1名を system_admin に昇格しておく。
 *
 * まず村上 飛羽を昇格。該当ユーザーがおらず system_admin が1人もいない場合は、
 * 既存の勤怠管理者(attendance_manager)を全員昇格させて管理不能状態を回避する。
 */
return new class extends Migration
{
    public function up(): void
    {
        $promoted = DB::table('users')
            ->where('name', '村上 飛羽')
            ->update(['attendance_role' => 'system_admin']);

        $hasSystemAdmin = DB::table('users')->where('attendance_role', 'system_admin')->exists();

        if ($promoted === 0 && ! $hasSystemAdmin) {
            // フォールバック：現行の勤怠管理者を初期システム管理者に昇格
            DB::table('users')
                ->where('attendance_role', 'attendance_manager')
                ->update(['attendance_role' => 'system_admin']);
        }
    }

    public function down(): void
    {
        // system_admin を勤怠管理者へ戻す（元の粒度には完全復元できないため近似）
        DB::table('users')
            ->where('attendance_role', 'system_admin')
            ->update(['attendance_role' => 'attendance_manager']);
    }
};
