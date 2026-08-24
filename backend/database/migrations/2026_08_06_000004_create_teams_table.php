<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đội nhóm cắt ngang cơ cấu phòng ban.
 *
 * Khác với `departments` (cây tổ chức cố định, quyết định phân quyền), đội nhóm
 * là tập hợp linh hoạt: một đội triển khai có thể gồm người từ Sale, Kỹ thuật
 * và Kế toán cùng lúc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();

            $table->foreignId('leader_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Một người chỉ được có mặt một lần trong cùng một đội.
            // Ràng buộc ở tầng database, không chỉ ở tầng ứng dụng.
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
