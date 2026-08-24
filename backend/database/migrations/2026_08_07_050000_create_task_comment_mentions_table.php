<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comment_mentions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('task_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Nhắc cùng một người hai lần trong một bình luận vẫn chỉ là một
            // lần được nhắc — nếu không, mục 1.6 sẽ gửi hai thông báo giống hệt.
            $table->unique(['task_comment_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comment_mentions');
    }
};
