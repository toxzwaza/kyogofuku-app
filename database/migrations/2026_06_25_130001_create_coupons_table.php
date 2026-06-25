<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('クーポン名');
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable()->comment('LINE送付時の表示画像（S3パス/URL）');
            $table->string('thumbnail_disk')->nullable()->comment('s3/public 等');
            $table->text('terms_text')->nullable()->comment('利用条件');
            $table->string('discount_type')->default('fixed')->comment('fixed（円）/ rate（%）');
            $table->integer('discount_value')->default(0)->comment('割引額(円) or 率(%)');
            $table->unsignedInteger('valid_days')->nullable()->comment('配布からN日有効');
            $table->date('valid_until_fixed')->nullable()->comment('固定有効期限（valid_daysと排他）');
            $table->boolean('combinable')->default(false)->comment('併用可否（true=併用可）');
            $table->string('status')->default('active')->comment('active/inactive/archived');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
