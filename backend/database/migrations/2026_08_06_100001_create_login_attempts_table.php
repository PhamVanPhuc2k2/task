<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký đăng nhập — yêu cầu ở README mục 1.9 Bảo mật.
 *
 * Ghi cả lần thành công lẫn thất bại, để trả lời được hai câu hỏi: "ai đăng
 * nhập từ đâu" và "tài khoản nào đang bị dò mật khẩu".
 *
 * Lưu `email` dạng chuỗi chứ không chỉ `user_id`: lần thử vào một email không
 * tồn tại cũng là tín hiệu cần biết, mà lúc đó chưa có user nào để trỏ tới.
 *
 * Chỉ ghi thêm, không sửa, không xoá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('email');

            $table->boolean('successful');
            // Vì sao thất bại: sai mật khẩu, tài khoản bị khoá, quá số lần thử.
            $table->string('failure_reason', 50)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['email', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['successful', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
