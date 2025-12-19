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
        Schema::create('task_expense_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('task_title')->comment('タスク名');
            $table->string('expense_category')->comment('費用項目');
            $table->integer('count')->default(1)->comment('使用回数');
            $table->timestamp('last_used_at')->nullable()->comment('最終使用日時');
            $table->timestamps();
            
            // タスク名と費用項目の組み合わせでユニーク制約
            $table->unique(['task_title', 'expense_category']);
            // 検索用インデックス
            $table->index('task_title');
            $table->index('expense_category');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('task_expense_mappings');
    }
};
