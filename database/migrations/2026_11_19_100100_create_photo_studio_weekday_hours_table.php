<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 前撮りスタジオの曜日ごとの営業時間（既定の時刻と違う曜日だけ行を持つ）。
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('photo_studio_weekday_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_studio_id')->constrained('photo_studios')->cascadeOnDelete()
                ->comment('前撮りスタジオID');
            $table->unsignedTinyInteger('weekday')->comment('曜日（0=日〜6=土。Carbon の dayOfWeek と同じ）');
            $table->time('start_time')->comment('その曜日の開始時刻（最初の枠）');
            $table->time('end_time')->comment('その曜日の終了時刻（最後の枠の終わり）');
            $table->timestamps();

            $table->unique(['photo_studio_id', 'weekday'], 'uq_photo_studio_weekday');
        });
    }

    public function down()
    {
        Schema::dropIfExists('photo_studio_weekday_hours');
    }
};
