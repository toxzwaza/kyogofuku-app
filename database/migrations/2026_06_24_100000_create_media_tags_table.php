<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('media_tags')
                ->cascadeOnDelete()
                ->comment('親タグID（null=ルート）');
            $table->string('name')->comment('タグ名');
            $table->unsignedInteger('sort_order')->default(0)->comment('同一階層内の表示順');
            $table->timestamps();

            // 同一階層に同名タグを作らせない
            $table->unique(['parent_id', 'name'], 'uq_media_tag_parent_name');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tags');
    }
};
