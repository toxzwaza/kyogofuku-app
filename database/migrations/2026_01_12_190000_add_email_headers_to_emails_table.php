<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('emails', function (Blueprint $table) {
            // SESのSMTP idを保存（参考用）
            $table->string('ses_smtp_id')->nullable()->after('message_id');
            
            // メールヘッダーから抽出した情報を保存
            $table->string('in_reply_to')->nullable()->after('subject');
            $table->text('references')->nullable()->after('in_reply_to');
            
            // インデックス追加
            $table->index('ses_smtp_id');
            $table->index('in_reply_to');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['ses_smtp_id']);
            $table->dropIndex(['in_reply_to']);
            $table->dropColumn(['ses_smtp_id', 'in_reply_to', 'references']);
        });
    }
};

